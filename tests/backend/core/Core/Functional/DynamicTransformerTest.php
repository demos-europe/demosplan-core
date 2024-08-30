<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;
use EDT\JsonApi\OutputHandling\DynamicTransformer;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use Illuminate\Support\Collection;
use League\Fractal\Manager;
use League\Fractal\ParamBag;
use League\Fractal\Resource\Item;
use League\Fractal\Scope;
use League\Fractal\Serializer\JsonApiSerializer;
use League\Fractal\TransformerAbstract;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;
use Tests\Base\MockMethodDefinition;

class DynamicTransformerTest extends JsonApiTest
{
    private const TYPE = 'Foobar';

    private const ID = 'id';

    private const PROCEDURE = 'procedure';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageBagInterface
     */
    protected $messageBag;

    /**
     * @var Manager
     */
    private $fractal;

    /**
     * @var ProcedureResourceType
     */
    private $procedureResourceType;

    public function testProcedureInclude(): void
    {
        self::markSkippedForCIIntervention();
        $this->loginTestUser();
        $this->enablePermissions(['feature_json_api_procedure']);

        $attributes = [
            self::ID => $this->createIdPropertyDefinition(),
        ];

        $includes = [
            self::PROCEDURE => $this->createIncludeDefinition(),
        ];
        $transformer = $this->createDynamicTransformer($attributes, $includes);

        self::assertEquals([self::PROCEDURE], $transformer->getAvailableIncludes());
        self::assertEquals([self::PROCEDURE], $transformer->getDefaultIncludes());

        $item = new Item($this->getInputData(), $transformer, self::TYPE);

        $outputData = $this->fractal->createData($item, self::TYPE);
        self::assertEquals(
            [
                'data'     => [
                    'type'          => self::TYPE,
                    'id'            => 'abc',
                    'attributes'    => new stdClass(),
                    'relationships' => [
                        'procedure' => [
                            'data' => [
                                'type' => 'Procedure',
                                'id'   => '',
                            ],
                        ],
                    ],
                ],
                'included' => [
                    0 => [
                        'type'       => 'Procedure',
                        'id'         => '',
                        'attributes' => [
                            'name'                   => 'My Procedure',
                            'agencyMainEmailAddress' => null,
                        ],
                    ],
                ],
            ],
            $outputData->toArray()
        );
        self::assertTrue(true);
    }

    /**
     * @dataProvider getProcedureIncludes()
     */
    public function testProcedureIncludesWithWarning($expectedMissingProperty, $requestedInclude): void
    {
        self::markSkippedForCIIntervention();
        $warnings = $this->getWarnings($requestedInclude, true);

        self::assertSame(
            'Faulty API request: The following requested includes are not available in the resource type \'Foobar\': `'.$expectedMissingProperty.'`. Available includes are: `procedure`.',
            $warnings->get('dev')->get(0)->getText()
        );
    }

    /**
     * @dataProvider getProcedureIncludes()
     */
    public function testProcedureIncludesWithoutWarning($expectedMissingProperty, $requestedInclude): void
    {
        self::markSkippedForCIIntervention();
        $warnings = $this->getWarnings($requestedInclude, false);
        self::assertEmpty($warnings->get('warning'));
    }

    /**
     * @dataProvider getProcedureIncludes()
     */
    public function testProcedureIncludesWithoutWarningProd($expectedMissingProperty, $requestedInclude): void
    {
        self::markSkippedForCIIntervention();
        // DX warnings should never show up in prod mode
        $warnings = $this->getWarnings($requestedInclude, true, DemosPlanKernel::ENVIRONMENT_PROD);
        self::assertEmpty($warnings->get('warning'));

        $warnings = $this->getWarnings($requestedInclude, false, DemosPlanKernel::ENVIRONMENT_PROD);
        self::assertEmpty($warnings->get('warning'));
    }

    public function testNonAllowedExclude(): void
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');

        $procedure = $this->getProcedureReference(
            LoadProcedureData::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE
        );
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $responseBody = $this->executeGetRequest(
            ProcedureResourceType::getName(),
            $procedure->getId(),
            $user,
            $procedure,
            ['exclude' => 'owningOrganisation'],
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame(
            'excluding relationships is not supported',
            $responseBody['errors'][0]['title']
        );
    }

    public function testNonAvailableInclude(): void
    {
        self::markSkippedForCIIntervention();
        // Can be enabled after an exception is thrown again instead of just showing a warning.

        $procedure = $this->getProcedureReference(
            LoadProcedureData::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE
        );
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $responseBody = $this->executeGetRequest(
            ProcedureResourceType::getName(),
            $procedure->getId(),
            $user,
            $procedure,
            ['include' => 'type'],
            Response::HTTP_BAD_REQUEST
        );

        self::assertStringStartsWith(
            "Include 'type' is not available for resource type 'Procedure'.",
            $responseBody['errors'][0]['title']
        );
    }

    /**
     * @dataProvider getIncudeTestData
     */
    public function testAllowedInclude(array $urlParameters, array $includeCounts): void
    {
        $procedure = $this->getProcedureReference(
            LoadProcedureData::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE
        );
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $responseBody = $this->executeGetRequest(
            ProcedureResourceType::getName(),
            $procedure->getId(),
            $user,
            $procedure,
            $urlParameters
        );

        foreach ($includeCounts as $includeType => $expectedCount) {
            $orgaIncludes = array_filter(
                $responseBody['included'],
                static function (array $include) use ($includeType): bool {
                    return $includeType === $include['type'];
                }
            );
            self::assertCount($expectedCount, $orgaIncludes);
        }
    }

    public function getIncudeTestData(): array
    {
        return [
            // #0
            [
                [
                    'include'           => 'owningOrganisation.customers',
                    'fields[Procedure]' => 'owningOrganisation',
                    'fields[Orga]'      => 'customers',
                ],
                [
                    'Orga'     => 1,
                    'Customer' => 1,
                ],
            ],
            // #1
            [
                [
                    'include'           => 'owningOrganisation.customers.signLanguageOverviewVideo',
                    'fields[Procedure]' => 'owningOrganisation',
                    'fields[Orga]'      => 'customers',
                    'fields[Customer]'  => 'signLanguageOverviewVideo',
                ],
                [
                    'Orga'                      => 1,
                    'Customer'                  => 1,
                    'SignLanguageOverviewVideo' => 0, // due to missing permission
                ],
            ],
            // #2
            [
                [
                    'include'           => 'owningOrganisation.customers.branding',
                    'fields[Procedure]' => 'owningOrganisation',
                    'fields[Orga]'      => 'customers',
                    'fields[Customer]'  => 'branding',
                ],
                [
                    'Orga'     => 1,
                    'Customer' => 1,
                    'Branding' => 0, // due to missing permission
                ],
            ],
            // #3
            [
                [
                    'include'           => 'owningOrganisation',
                    'fields[Procedure]' => 'owningOrganisation',
                ],
                [
                    'Orga' => 1,
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fractal = new Manager();

        $jsonApiSerializer = new JsonApiSerializer();

        $this->fractal->setSerializer($jsonApiSerializer);
        $this->fractal->setRecursionLimit(20);
        $this->fractal->parseIncludes([]);
        $this->fractal->parseFieldsets([]);
        $this->fractal->parseExcludes([]);

        $this->procedureResourceType = $this->getContainer()->get(ProcedureResourceType::class);
        $this->messageBag = $this->getContainer()->get(MessageBagInterface::class);
        $this->logger = $this->getContainer()->get(LoggerInterface::class);
    }

    private function createIdPropertyDefinition(): PropertyDefinitionInterface
    {
        return new class() implements PropertyDefinitionInterface {
            public function determineData($entity, ParamBag $params)
            {
                return $entity->id;
            }

            public function isToBeUsedAsDefaultField(): bool
            {
                return true;
            }
        };
    }

    private function getInputData(): object
    {
        $procedure = new Procedure();
        $procedure->setName('My Procedure');

        $inputData = new stdClass();
        $inputData->id = 'abc';
        $inputData->a = 1;
        $inputData->b = 2;
        $inputData->c = 3;
        $inputData->procedure = $procedure;

        return $inputData;
    }

    private function createIncludeDefinition(): IncludeDefinitionInterface
    {
        return new class($this->procedureResourceType) implements IncludeDefinitionInterface {
            /**
             * @var ResourceTypeInterface
             */
            private $resourceType;

            public function __construct(ResourceTypeInterface $resourceType)
            {
                $this->resourceType = $resourceType;
            }

            public function isToMany($propertyData): bool
            {
                return is_iterable($propertyData);
            }

            public function getTransformer(): TransformerAbstract
            {
                return $this->resourceType->getTransformer();
            }

            public function getResourceKey(): string
            {
                return $this->resourceType::getName();
            }

            public function determineData($entity, ParamBag $params)
            {
                return $entity->procedure;
            }

            public function isToBeUsedAsDefaultField(): bool
            {
                return true;
            }

            public function isToBeUsedAsDefaultInclude(): bool
            {
                return true;
            }
        };
    }

    private function getWarnings(string $requestedInclude, bool $isRequested, string $env = DemosPlanKernel::ENVIRONMENT_DEV): Collection
    {
        $attributes = [
            self::ID => $this->createIdPropertyDefinition(),
        ];
        $includes = [self::PROCEDURE => $this->getMock(IncludeDefinition::class)];
        $transformer = $this->createDynamicTransformer($attributes, $includes, $env);

        $managerMock = $this->getMock(
            Manager::class,
            [new MockMethodDefinition('getRequestedIncludes', [$requestedInclude])]
        );

        $mockMethods = [
            new MockMethodDefinition('getManager', $managerMock),
            new MockMethodDefinition('isRequested', $isRequested),
        ];
        /** @var Scope $mockScope */
        $mockScope = $this->getMock(Scope::class, $mockMethods);

        $transformer->validateIncludes($mockScope);

        return $this->messageBag->get();
    }

    public function getProcedureIncludes(): array
    {
        return [
            [
                'foo',
                'foo.bar',
            ],
            [
                'foo',
                'foo.bar.baz',
            ],
            [
                '',
                '',
            ],
            [
                '1234',
                '1234.1234',
            ],
            [
                '1234',
                '1234.asdf',
            ],
        ];
    }

    private function createDynamicTransformer(array $attributes, array $includes, string $env = DemosPlanKernel::ENVIRONMENT_DEV): DynamicTransformer
    {
        $mockMethods = [
            new MockMethodDefinition('getKernelEnvironment', $env),
        ];

        $globalConfig = $this->getMock(GlobalConfigInterface::class, $mockMethods);
        $apiLogger = new ApiLogger($globalConfig, $this->logger, $this->messageBag);

        return new DynamicTransformer(
            self::TYPE,
            $attributes,
            $includes,
            new MessageFormatter(),
            $apiLogger
        );
    }
}
