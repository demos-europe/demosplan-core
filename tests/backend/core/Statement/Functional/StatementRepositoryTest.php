<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Tests\Base\FunctionalTestCase;

class StatementRepositoryTest extends FunctionalTestCase
{
    /**
     * @var StatementRepository
     */
    protected $sut;

    /**
     * @var StatementFragment
     */
    protected $testStatementFragment;

    /**
     * @var User
     */
    protected $testUser;

    /**
     * @var Procedure
     */
    protected $testProcedure;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(StatementRepository::class);
        $this->testStatementFragment = $this->fixtures->getReference('testStatementFragment1');
        $this->testUser = $this->fixtures->getReference('testUser');
        $this->logIn($this->testUser);
        $this->enablePermissions(['feature_statements_fragment_edit']);
        $this->testProcedure = $this->fixtures->getReference('testProcedure');
    }

    public function testEqualsIdOrEqualsId()
    {
        $expectedStatementA = $this->getStatementReference('testStatement20');
        $expectedIdA = $expectedStatementA->getId();
        $expectedStatementB = $this->getStatementReference('testStatement1');
        $expectedIdB = $expectedStatementB->getId();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->anyConditionApplies()
            ->propertyHasValue($expectedIdA, ['id'])
            ->propertyHasValue($expectedIdB, ['id']);

        $actualStatements = $query->getEntities();

        self::assertCount(2, $actualStatements);
        self::assertContains($expectedStatementA, $actualStatements);
        self::assertContains($expectedStatementB, $actualStatements);
    }

    public function testEqualsSubmitNameAndEqualsAuthorNameAndEqualsId()
    {
        $expectedStatement = $this->getStatementReference('testStatement2');
        $expectedId = $expectedStatement->getId();
        $expectedAuthorName = $expectedStatement->getMeta()->getAuthorName();
        $expectedSubmitName = $expectedStatement->getMeta()->getSubmitName();
        self::assertIsString($expectedAuthorName);
        self::assertIsString($expectedSubmitName);

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue($expectedAuthorName, ['meta', 'authorName'])
            ->propertyHasValue($expectedSubmitName, ['meta', 'submitName'])
            ->propertyHasValue($expectedId, ['id']);

        $actualStatements = $query->getEntities();

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    public function testEqualsIdAndProcedureId()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();
        $expectedProcedureId = $expectedStatement->getProcedure()->getId();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue($expectedId, ['id'])
            ->inProcedureWithId($expectedProcedureId);

        $actualStatements = $query->getEntities();

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    public function testEqualsIdAndProcedureOrgaId()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();
        $expectedProcedureOrgaId = $expectedStatement->getProcedure()->getOrga()->getId();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue($expectedProcedureOrgaId, ['procedure', 'orga', 'id'])
            ->propertyHasValue($expectedId, ['id']);

        $actualStatements = $query->getEntities();

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    public function testEqualsMetaStatementId()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue($expectedId, ['meta', 'statement', 'id']);

        $actualStatements = $query->getEntities();

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    public function testEqualsMetaStatementIdRecursive()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue(
                $expectedId,
                [
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'meta',
                    'statement',
                    'id',
                ]
            );

        $actualStatements = $query->getEntities();

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    public function testBetweenAnonymVotes()
    {
        $expectedStatement = $this->getStatementReference('testStatement');
        $expectedId = $expectedStatement->getId();
        $expectedVotes = $expectedStatement->getNumberOfAnonymVotes();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyBetweenValuesInclusive($expectedVotes - 1, $expectedVotes + 1, ['numberOfAnonymVotes'])
            ->propertyHasValue($expectedId, ['id']);

        $actualStatements = $query->getEntities();

        self::assertCount(1, $actualStatements);
        self::assertContains($expectedStatement, $actualStatements);
    }

    public function testEqualsIdOrEqualsSubmitNameAndEqualsAuthorName()
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

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->anyConditionApplies()
            ->propertyHasValue($expectedIdB, ['id'])
            ->allConditionsApply()
            ->propertyHasValue($authorNameA, ['meta', 'authorName'])
            ->propertyHasValue($submitNameA, ['meta', 'submitName'])
            ->propertyHasValue($expectedIdA, ['id']);

        $actualStatements = $query->getEntities();

        self::assertCount(2, $actualStatements);
        self::assertContains($expectedStatementA, $actualStatements);
        self::assertContains($expectedStatementB, $actualStatements);
    }

    public function testNotEqualsId()
    {
        $unexpectedStatement = $this->getStatementReference('testStatement20');
        $unexpectedId = $unexpectedStatement->getId();

        $query = $this->sut->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasNotValue($unexpectedId, ['id']);

        $actualStatements = $query->getEntities();

        self::assertNotContains($unexpectedId, $actualStatements);
    }
}
