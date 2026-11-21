<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Api;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use demosplan\DemosPlanCoreBundle\Api\Common\SparseFieldsetOpenApiFactory;
use PHPUnit\Framework\TestCase;

class SparseFieldsetOpenApiFactoryTest extends TestCase
{
    private ?SparseFieldsetOpenApiFactory $sut = null;

    public function testReplacesPropertiesParameterWithJsonApiFieldsParameter(): void
    {
        $this->createSut(['/api/3.0/StatementSegment' => $this->getOperation(['StatementSegment'])]);

        $parameters = $this->getGetParameters('/api/3.0/StatementSegment');

        self::assertSame(['page', 'fields[StatementSegment]', 'order[externId]'], $this->getNames($parameters));
    }

    public function testFieldsParameterIsAQueryStringWithDescription(): void
    {
        $this->createSut(['/api/3.0/Tag' => $this->getOperation(['Tag'])]);

        $fieldsParameter = $this->getGetParameters('/api/3.0/Tag')[1];

        self::assertSame('query', $fieldsParameter->getIn());
        self::assertSame(['type' => 'string'], $fieldsParameter->getSchema());
        self::assertStringContainsString('fields[Tag]=', $fieldsParameter->getDescription());
    }

    public function testKeepsOperationWithoutPropertiesParameterUntouched(): void
    {
        $operation = new Operation(tags: ['Tag'], parameters: [new Parameter('page', 'query')]);
        $this->createSut(['/api/3.0/Tag' => $operation]);

        self::assertSame(['page'], $this->getNames($this->getGetParameters('/api/3.0/Tag')));
    }

    public function testKeepsPropertiesParameterIfResourceNameIsUnknown(): void
    {
        $this->createSut(['/api/3.0/Unknown' => $this->getOperation(null)]);

        self::assertContains('properties[]', $this->getNames($this->getGetParameters('/api/3.0/Unknown')));
    }

    public function testIgnoresPropertiesParameterThatIsNotInTheQuery(): void
    {
        $operation = new Operation(tags: ['Tag'], parameters: [new Parameter('properties[]', 'header')]);
        $this->createSut(['/api/3.0/Tag' => $operation]);

        self::assertSame(['properties[]'], $this->getNames($this->getGetParameters('/api/3.0/Tag')));
    }

    public function testKeepsPathWithoutGetOperationUntouched(): void
    {
        $paths = new Paths();
        $paths->addPath('/api/ai/auth/login_check', new PathItem(post: new Operation(tags: ['Login'])));
        $this->sut = new SparseFieldsetOpenApiFactory($this->getInnerFactory($paths));

        $pathItem = ($this->sut)()->getPaths()->getPath('/api/ai/auth/login_check');

        self::assertNull($pathItem?->getGet());
        self::assertSame(['Login'], $pathItem?->getPost()?->getTags());
    }

    /**
     * @param list<string>|null $tags
     */
    private function getOperation(?array $tags): Operation
    {
        return new Operation(
            tags: $tags,
            parameters: [
                new Parameter('page', 'query'),
                new Parameter('properties[]', 'query', 'Allows you to reduce the response'),
                new Parameter('order[externId]', 'query'),
            ],
        );
    }

    /**
     * @param array<string, Operation> $getOperationsByPath
     */
    private function createSut(array $getOperationsByPath): void
    {
        $paths = new Paths();
        foreach ($getOperationsByPath as $path => $operation) {
            $paths->addPath($path, new PathItem(get: $operation));
        }

        $this->sut = new SparseFieldsetOpenApiFactory($this->getInnerFactory($paths));
    }

    private function getInnerFactory(Paths $paths): OpenApiFactoryInterface
    {
        $openApi = new OpenApi(new Info('test', '1.0.0'), [], $paths);

        return new class($openApi) implements OpenApiFactoryInterface {
            public function __construct(private readonly OpenApi $openApi)
            {
            }

            public function __invoke(array $context = []): OpenApi
            {
                return $this->openApi;
            }
        };
    }

    /**
     * @return list<Parameter>
     */
    private function getGetParameters(string $path): array
    {
        self::assertNotNull($this->sut);
        $parameters = ($this->sut)()->getPaths()->getPath($path)?->getGet()?->getParameters();
        self::assertIsArray($parameters);

        return array_values($parameters);
    }

    /**
     * @param list<Parameter> $parameters
     *
     * @return list<string>
     */
    private function getNames(array $parameters): array
    {
        return array_map(static fn (Parameter $parameter): string => $parameter->getName(), $parameters);
    }
}
