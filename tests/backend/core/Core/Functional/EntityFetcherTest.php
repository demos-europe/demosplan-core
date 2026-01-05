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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\Query\QueryException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\Contracts\FunctionInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class EntityFetcherTest extends FunctionalTestCase
{
    /**
     * @var EntityFetcher
     */
    protected $sut;

    /**
     * @var ProcedureResourceType
     */
    private $procedureResourceType;

    /**
     * @var StatementResourceType
     */
    private $statementResourceType;

    /**
     * @var Procedure
     */
    private Procedure|Proxy|null $testProcedure;

    /**
     * @var JsonApiSortingParser
     */
    private $sortingParser;

    /**
     * @var FunctionInterface
     */
    private $condition;

    /**
     * @var DrupalFilterParser
     */
    private $filterParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeCoreComponents();
        $this->setupTestEnvironment();
    }

    /**
     * Initialize core components required for tests.
     */
    private function initializeCoreComponents(): void
    {
        $this->sut = $this->getContainer()->get(EntityFetcher::class);
        $this->procedureResourceType = $this->getContainer()->get(ProcedureResourceType::class);
        $this->statementResourceType = $this->getContainer()->get(StatementResourceType::class);
        $this->sortingParser = $this->getContainer()->get(JsonApiSortingParser::class);
        $this->filterParser = $this->getContainer()->get(DrupalFilterParser::class);
        $this->condition = $this->getContainer()->get(DqlConditionFactory::class)->true();
    }

    /**
     * Setup the test environment, including entities and permissions.
     */
    private function setupTestEnvironment(): void
    {
        $customerService = $this->getContainer()->get(CustomerService::class);
        $currentCustomer = $customerService->getCurrentCustomer();
        $orga = OrgaFactory::createOne();
        $this->testProcedure = ProcedureFactory::createOne(['orga' => $orga, 'customer' => $currentCustomer]);
        ProcedureSettingsFactory::createOne(['procedure' => $this->testProcedure]);
        $this->setupProcedureWithStatements();
        $this->setupTestUser($orga);
        $this->setCurrentProcedureService();
        $this->loginTestUser();
        $this->enablePermissions([
            'feature_json_api_statement',
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);
    }

    /**
     * Setup test user with organization.
     */
    private function setupTestUser($orga): void
    {
        $testUser = $this->getUserReference('testUser');
        $testUser->setOrga($orga->_real());
    }

    /**
     * Set the current procedure service with the test procedure.
     */
    private function setCurrentProcedureService(): void
    {
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->testProcedure->_real());
        $this->procedureResourceType->setCurrentProcedureService($currentProcedureService);
        $this->statementResourceType->setCurrentProcedureService($currentProcedureService);
    }

    private function setupProcedureWithStatements(): void
    {
        // Create Multiple Statements with StatementMeta and associate them with the Procedure
        $submitNames = ['Charlie', 'Bravo', 'Delta', 'Alpha']; // Example submitNames for sorting
        foreach ($submitNames as $submitName) {
            $originalStatement = StatementFactory::new()->create(['procedure' => $this->testProcedure]);
            $statement = StatementFactory::new()->create(['procedure' => $this->testProcedure, 'original' => $originalStatement]);
            StatementMetaFactory::new()->create(['submitName' => $submitName, 'statement' => $statement]);
        }
    }

    public function testGetEntityByIdentifier(): void
    {
        $this->enablePermissions(['area_admin_procedures', 'area_search_submitter_in_procedures']);
        $actual = $this->procedureResourceType->getEntity($this->testProcedure->getId());
        self::assertSame($this->testProcedure->_real(), $actual);
    }

    public function testListStatementsBySubmitName(): void
    {
        $sortMethods = $this->sortingParser->createFromQueryParamValue('submitName');

        $referenceStatements = $this->getStatementListSortedBySubmitName($this->testProcedure->getId(), 'submitName');
        $statements = $this->statementResourceType->getEntities([$this->condition], $sortMethods);

        static::assertSameSize($referenceStatements, $statements);
        $count = 0;
        foreach ($referenceStatements as $referenceStatement) {
            static::assertSame($referenceStatement->getMeta()->getSubmitName(), $statements[$count]->getMeta()->getSubmitName());
            ++$count;
        }
    }

    public function testListStatementsBySubmitDate(): void
    {
        $sortMethods = $this->sortingParser->createFromQueryParamValue('submitDate');

        $referenceStatements = $this->getStatementListSortedBySubmitName($this->testProcedure->getId(), 'submitDate');
        $statements = $this->statementResourceType->getEntities([$this->condition], $sortMethods);

        static::assertSameSize($referenceStatements, $statements);
        $count = 0;
        foreach ($referenceStatements as $referenceStatement) {
            static::assertSame($referenceStatement->getSubmit(), $statements[$count]->getSubmit());
            ++$count;
        }
    }

    public function testListStatementsByOrganisationName(): void
    {
        $sortMethods = $this->sortingParser->createFromQueryParamValue('initialOrganisationName');

        $referenceStatements = $this->getStatementListSortedBySubmitName($this->testProcedure->getId(), 'initialOrganisationName');
        $statements = $this->statementResourceType->getEntities([$this->condition], $sortMethods);

        static::assertSameSize($referenceStatements, $statements);
        $count = 0;
        foreach ($referenceStatements as $referenceStatement) {
            static::assertSame($referenceStatement->getMeta()->getOrgaName(), $statements[$count]->getMeta()->getOrgaName());
            ++$count;
        }
    }

    public function testListProceduresUnrestricted(): void
    {
        $procedures = $this->sut->listEntitiesUnrestricted(Procedure::class, []);
        self::assertNotEmpty($procedures);
    }

    public function testListProcedures(): void
    {
        $procedures = $this->procedureResourceType->getEntities([], []);
        self::assertNotEmpty($procedures);
    }

    /**
     * @return array<int, Statement>
     */
    private function getStatementListSortedBySubmitName(string $procedureId, string $sortBy): array
    {
        $referenceStatements = $this->getEntries(Statement::class,
            [
                'procedure'     => $procedureId,
                'deleted'       => false,
                'headStatement' => null,
            ]);

        $referenceStatements = collect($referenceStatements)->filter(
            static function (Statement $statement): bool {
                return !$statement->isOriginal();
            }
        );
        switch ($sortBy) {
            case 'submitName':
                $referenceStatements = $referenceStatements->sortBy(
                    static function (Statement $statement) {
                        return $statement->getMeta()->getSubmitName();
                    }
                );
                break;
            case 'initialOrganisationName':
                $referenceStatements = $referenceStatements->sortBy(
                    static function (Statement $statement) {
                        return $statement->getMeta()->getOrgaName();
                    }
                );
                break;
            case 'submitDate':
                $referenceStatements = $referenceStatements->sortBy(
                    static function (Statement $statement) {
                        return $statement->getSubmit();
                    }
                );
                break;
        }

        $referenceStatements = $referenceStatements->all();

        // check result:
        /** @var Statement $statement */
        foreach ($referenceStatements as $statement) {
            static::assertNotNull($statement->getOriginal());
            static::assertNull($statement->getHeadStatement());
            static::assertFalse($statement->isInCluster());
            static::assertFalse($statement->isDeleted());
            static::assertSame($procedureId, $statement->getProcedureId());
        }

        return $referenceStatements;
    }

    public function testEqualsIdOrEqualsIdWithNormalizer()
    {
        $expectedStatementA = $this->getStatementReference('testStatement20');
        $expectedIdA = $expectedStatementA->getId();
        $expectedStatementB = $this->getStatementReference('testStatement1');
        $expectedIdB = $expectedStatementB->getId();
        $filters = [
            'condition_a' => [
                'condition' => [
                    'operator' => '=',
                    'path'     => 'id',
                    'value'    => $expectedIdA,
                    'memberOf' => 'group_or',
                ],
            ],
            'condition_b' => [
                'condition' => [
                    'operator' => '=',
                    'path'     => 'id',
                    'value'    => $expectedIdB,
                    'memberOf' => 'group_or',
                ],
            ],
            'group_or'    => [
                'group' => [
                    'conjunction' => 'OR',
                ],
            ],
        ];
        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(2, $actualStatements);
        self::assertContains($expectedStatementA, $actualStatements);
        self::assertContains($expectedStatementB, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testEqualsSubmitNameAndEqualsAuthorNameAndEqualsIdWithNormalizer()
    {
        $expectedStatement = $this->getStatementReference('testStatement2');
        $expectedId = $expectedStatement->getId();
        $expectedAuthorName = $expectedStatement->getMeta()->getAuthorName();
        $expectedSubmitName = $expectedStatement->getMeta()->getSubmitName();
        self::assertIsString($expectedAuthorName);
        self::assertIsString($expectedSubmitName);

        $filters = [
            'condition_a' => [
                'condition' => [
                    'operator' => '=',
                    'path'     => 'meta.authorName',
                    'value'    => $expectedAuthorName,
                ],
            ],
            'condition_b' => [
                'condition' => [
                    'path'  => 'meta.submitName',
                    'value' => $expectedSubmitName,
                ],
            ],
            'condition_c' => [
                'condition' => [
                    'path'  => 'id',
                    'value' => $expectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testEqualsIdAndProcedureIdWithNormalizer()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();
        $expectedProcedureId = $expectedStatement->getProcedure()->getId();

        $filters = [
            'condition_a' => [
                'condition' => [
                    'path'  => 'procedure.id',
                    'value' => $expectedProcedureId,
                ],
            ],
            'condition_b' => [
                'condition' => [
                    'path'  => 'id',
                    'value' => $expectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testEqualsIdAndProcedureOrgaIdWithNormalizer()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();
        $expectedProcedureOrgaId = $expectedStatement->getProcedure()->getOrga()->getId();

        $filters = [
            'condition_a' => [
                'condition' => [
                    'path'  => 'procedure.orga.id',
                    'value' => $expectedProcedureOrgaId,
                ],
            ],
            'condition_b' => [
                'condition' => [
                    'path'  => 'id',
                    'value' => $expectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testEqualsMetaStatementIdWithNormalizer()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();

        $filters = [
            'condition_a' => [
                'condition' => [
                    'path'  => 'meta.statement.id',
                    'value' => $expectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testEqualsMetaStatementRecursiveWithNormalizer()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();

        $filters = [
            'condition_a' => [
                'condition' => [
                    'path'  => 'meta.statement.meta.statement.meta.statement.meta.statement.meta.statement.meta.statement.meta.statement.meta.statement.id',
                    'value' => $expectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testBetweenAnonymVotesWithNormalizer()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();
        $expectedVotes = $expectedStatement->getNumberOfAnonymVotes();

        $filters = [
            'condition_a' => [
                'condition' => [
                    'operator' => 'BETWEEN',
                    'path'     => 'numberOfAnonymVotes',
                    'value'    => [$expectedVotes - 1, $expectedVotes + 1],
                ],
            ],
            'condition_b' => [
                'condition' => [
                    'path'  => 'id',
                    'value' => $expectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testEqualsIdOrEqualsSubmitNameAndEqualsAuthorNameWithNormalizer()
    {
        $expectedStatementA = $this->getStatementReference('testStatement20');
        $expectedStatementB = $this->getStatementReference('testStatement2');
        $expectedIdA = $expectedStatementA->getId();
        $expectedIdB = $expectedStatementB->getId();
        $authorNameA = $expectedStatementA->getMeta()->getAuthorName();
        $authorNameB = $expectedStatementB->getMeta()->getAuthorName();
        $submitNameA = $expectedStatementA->getMeta()->getSubmitName();
        $submitNameB = $expectedStatementB->getMeta()->getSubmitName();

        self::assertNotSame($authorNameB, $authorNameA);
        self::assertNotSame($submitNameB, $submitNameA);

        $filters = [
            'condition_a' => [
                'condition' => [
                    'path'     => 'meta.authorName',
                    'value'    => $authorNameA,
                    'memberOf' => 'group_and',
                ],
            ],
            'condition_b' => [
                'condition' => [
                    'path'     => 'meta.submitName',
                    'value'    => $submitNameA,
                    'memberOf' => 'group_and',
                ],
            ],
            'group_and'   => [
                'group' => [
                    'conjunction' => 'AND',
                    'memberOf'    => 'group_or',
                ],
            ],
            'condition_c' => [
                'condition' => [
                    'path'     => 'id',
                    'value'    => $expectedIdA,
                    'memberOf' => 'group_and',
                ],
            ],
            'condition_d' => [
                'condition' => [
                    'path'     => 'id',
                    'value'    => $expectedIdB,
                    'memberOf' => 'group_or',
                ],
            ],
            'group_or'    => [
                'group' => [
                    'conjunction' => 'OR',
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertCount(2, $actualStatements);
        self::assertContains($expectedStatementA, $actualStatements);
        self::assertContains($expectedStatementB, $actualStatements);
    }

    /**
     * @throws QueryException
     */
    public function testNotEqualsIdWithNormalizer()
    {
        $unexpectedStatement = $this->getStatementReference('testStatement20');
        $unexpectedId = $unexpectedStatement->getId();

        $filters = [
            'condition_a' => [
                'condition' => [
                    'operator' => '<>',
                    'path'     => 'id',
                    'value'    => $unexpectedId,
                ],
            ],
        ];

        $filters = $this->filterParser->validateFilter($filters);
        $filters = $this->filterParser->parseFilter($filters);

        $actualStatements = $this->sut->listEntitiesUnrestricted(Statement::class, $filters);

        self::assertNotContains($unexpectedId, $actualStatements);
    }
}
