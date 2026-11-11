<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Common;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Documents the JSON:API sparse fieldset syntax in the OpenAPI docs.
 *
 * The resources use the PropertyFilter, which documents itself as `properties[]`. For JSON:API
 * requests the parameter that is actually used is `fields[<ResourceName>]=a,b,c`, which the
 * JsonApiProvider translates into that same filter. This replaces the documented `properties[]`
 * parameter on collection operations with `fields[<ResourceName>]`.
 */
final class SparseFieldsetOpenApiFactory implements OpenApiFactoryInterface
{
    private const PROPERTIES_PARAMETER_NAME = 'properties[]';

    public function __construct(private readonly OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            $paths->addPath($path, $this->replaceInPathItem($pathItem));
        }

        return $openApi;
    }

    private function replaceInPathItem(PathItem $pathItem): PathItem
    {
        $getOperation = $pathItem->getGet();
        if (null === $getOperation) {
            return $pathItem;
        }

        return $pathItem->withGet($this->replaceInOperation($getOperation));
    }

    private function replaceInOperation(Operation $operation): Operation
    {
        $parameters = $operation->getParameters();
        $resourceName = $operation->getTags()[0] ?? null;
        if (null === $parameters || null === $resourceName) {
            return $operation;
        }

        return $operation->withParameters(array_map(
            fn (mixed $parameter): mixed => $this->isPropertiesParameter($parameter)
                ? $this->createFieldsParameter($resourceName)
                : $parameter,
            $parameters
        ));
    }

    private function isPropertiesParameter(mixed $parameter): bool
    {
        return $parameter instanceof Parameter
            && 'query' === $parameter->getIn()
            && self::PROPERTIES_PARAMETER_NAME === $parameter->getName();
    }

    private function createFieldsParameter(string $resourceName): Parameter
    {
        $key = sprintf('fields[%s]', $resourceName);

        return new Parameter(
            name: $key,
            in: 'query',
            description: sprintf(
                'Allows you to reduce the response to contain only the fields you need, as a comma-separated list. Example: %s=field,anotherField',
                $key
            ),
            schema: ['type' => 'string'],
        );
    }
}
