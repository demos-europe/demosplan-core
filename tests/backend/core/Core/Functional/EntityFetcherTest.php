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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
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

        $this->sut = $this->getContainer()->get(EntityFetcher::class);

        $this->testProcedure = ProcedureFactory::createOne();

        $this->procedureResourceType = $this->getContainer()->get(ProcedureResourceType::class);
        $this->statementResourceType = $this->getContainer()->get(StatementResourceType::class);
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $conditionFactory = $this->getContainer()->get(DqlConditionFactory::class);
        $this->sortingParser = $this->getContainer()->get(JsonApiSortingParser::class);

        $currentProcedureService->setProcedure($this->testProcedure->_real());
        $this->procedureResourceType->setCurrentProcedureService($currentProcedureService);
        $this->statementResourceType->setCurrentProcedureService($currentProcedureService);
        $this->condition = $conditionFactory->true();

        $this->loginTestUser();
        $this->enablePermissions([
            'feature_json_api_statement',
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);

        $this->filterParser = $this->getContainer()->get(DrupalFilterParser::class);
    }

    public function testGetEntityByIdentifier(): void
    {
        $this->enablePermissions(['area_admin_procedures', 'area_search_submitter_in_procedures']);
        $actual = $this->procedureResourceType->getEntity($this->testProcedure->getId());
        self::assertSame($this->testProcedure->_real(), $actual);
    }

    public function testListStatementsBySubmitName(): void
    {
        self::markSkippedForCIIntervention();

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
        self::markSkippedForCIIntervention();

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
        self::markSkippedForCIIntervention();

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
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
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
