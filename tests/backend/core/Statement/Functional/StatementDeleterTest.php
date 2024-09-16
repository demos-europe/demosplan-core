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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\CountyFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\MunicipalityFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\PriorityAreaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementAttributeFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Tests\Base\FunctionalTestCase;

class StatementDeleterTest extends FunctionalTestCase
{
    /** @var StatementDeleter */
    protected $sut;

    protected ?StatementService $statementService;
    protected ?StatementCopier $statementCopier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementDeleter::class);
        $this->statementService = $this->getContainer()->get(StatementService::class);
        $this->statementCopier = $this->getContainer()->get(StatementCopier::class);
        $user = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $this->logIn($user);
    }

    public function testDoNotEmtpyInternIdOfOriginalInCaseOfDeleteLastChild(): void
    {
        $this->enablePermissions(['feature_auto_delete_original_statement']);

        $testStatement = $this->getStatementReference('testFixtureStatement');
        $testStatementId = $testStatement->getId();
        $relatedOriginal = $testStatement->getOriginal();
        $numberOfChildrenBefore = $relatedOriginal->getChildren()->count();

        static::assertInstanceOf(Statement::class, $relatedOriginal);
        static::assertNotNull($testStatement->getInternId());
        static::assertNotNull($relatedOriginal->getInternId());
        static::assertGreaterThan(1, $numberOfChildrenBefore);

        $this->sut->deleteStatementObject($testStatement);
        static::assertNull($this->find(Statement::class, $testStatementId));
        static::assertNotNull($testStatement->getInternId());
        static::assertCount($numberOfChildrenBefore - 1, $relatedOriginal->getChildren());
    }

    public function testDeleteStatementButNotCopyOfStatement(): void
    {
        $testStatement2 = $this->getStatementReference('testStatement2');
        $testStatementId = $testStatement2->getId();

        $createdCopy = $this->statementCopier->copyStatementObjectWithinProcedure($testStatement2);
        static::assertInstanceOf(Statement::class, $createdCopy);

        $result2 = $this->sut->deleteStatementObject($testStatement2);
        static::assertTrue($result2);
        static::assertNull($this->statementService->getStatement($testStatementId));

        $createdCopy = $this->statementService->getStatement($createdCopy->getId());
        static::assertInstanceOf(Statement::class, $createdCopy);
    }

    /**
     * testDeleteStatementWithSimilarStatementSubmitters
     * Cover deletion of a single statement with related procedure person, by just asserting that the statement
     * is correctly deleted.
     */
    public function testDeleteStatementWithRelatedProcedurePerson()
    {
        $testStatement = $this->getStatementReference('testFixtureStatement');
        $testStatementId = $testStatement->getId();
        static::assertGreaterThan(0, $testStatement->getSimilarStatementSubmitters()->count());

        $deleted = $this->sut->deleteStatementObject($testStatement);
        static::assertTrue($deleted);
        $testStatement = $this->find(Statement::class, $testStatementId);
        static::assertNull($testStatement);
    }

    /**
     * Cover deletion of related ProcedurePerson on deletion of a Statement.
     */
    public function testCascadeDeleteRelatedSubmitterOnDeleteStatement()
    {
        $testStatement = $this->getStatementReference('testFixtureStatement');
        $testStatementId = $testStatement->getId();
        $submitters = $testStatement->getSimilarStatementSubmitters();
        static::assertGreaterThan(0, $submitters->count());
        $relatedSubmittersIds =
            collect($submitters)->map(fn (ProcedurePerson $procedurePerson) => $procedurePerson->getId());

        $deleted = $this->sut->deleteStatementObject($testStatement);
        static::assertTrue($deleted);
        $testStatement = $this->find(Statement::class, $testStatementId);
        static::assertNull($testStatement);

        // orphan removal deletes "detached" ProcedurePerson, even if another Statement is connected!
        foreach ($relatedSubmittersIds as $id) {
            static::assertNull($this->find(ProcedurePerson::class, $id));
        }
    }

    public function testDeleteStatement(): void
    {
        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $testStatement2 = $this->getStatementReference('testStatement2');
        static::assertInstanceOf(StatementMeta::class, $testStatement2->getMeta());

        $amountOfMetasBefore = $this->countEntries(StatementMeta::class);

        $amountOfTagsBefore = count($testStatement2->getTags());
        $entireAmountOfTagsBefore = count($this->getEntries(Tag::class));

        $initialAmountOfStatementsOfTag1 = count($testTag1->getStatements());
        $this->statementService->addTagToStatement($testTag1, $testStatement2);

        // total amount of tags in DB has not changed
        static::assertCount($entireAmountOfTagsBefore, $this->getEntries(Tag::class));
        static::assertCount($initialAmountOfStatementsOfTag1 + 1, $testTag1->getStatements());
        static::assertContains($testStatement2, $testTag1->getStatements());
        static::assertContains($testTag1, $testStatement2->getTags());
        $tags = $testStatement2->getTags();
        static::assertCount($amountOfTagsBefore + 1, $tags);

        // the actually deletion:
        $result = $this->sut->deleteStatementObject($testStatement2);

        static::assertTrue($result);
        static::assertCount($initialAmountOfStatementsOfTag1, $testTag1->getStatements());
        static::assertNotContains($testStatement2, $testTag1->getStatements());
        // total amount of tags in DB has still not changed
        static::assertCount($entireAmountOfTagsBefore, $this->getEntries(Tag::class));

        // total amount of StatementMeta in DB is decremeted
        static::assertSame(
            $amountOfMetasBefore - 1,
            $this->countEntries(StatementMeta::class)
        );
    }

    /**
     * Tests the database-side cascading delete functionality.
     *
     * This test verifies that when a `Statement` object is deleted, related entities such as
     * `StatementAttribute` are also deleted via database-side cascading, while other related entities
     * like `County`, `Municipality`, and `PriorityArea` remain unaffected in the database.
     *
     * The test creates a `Statement` object linked with one each of `County`, `Municipality`,
     * `PriorityArea`, and `StatementAttribute`. It then deletes the `Statement` object and checks:
     * - The related `StatementAttribute` is also deleted (verifying cascading delete).
     * - The counts of `County`, `Municipality`, and `PriorityArea` entities in the database remain unchanged,
     *   indicating they are not deleted (verifying non-cascading behavior for these entities).
     */
    public function testDBSitedCasading(): void
    {
        $originalStatement = StatementFactory::createOne();
        $county = CountyFactory::createOne();
        $municipality = MunicipalityFactory::createOne();
        $priorityArea = PriorityAreaFactory::createOne();

        $testStatement = StatementFactory::createOne([
            'counties'       => [$county],
            'municipalities' => [$municipality],
            'priorityAreas'  => [$priorityArea],
            'original'       => $originalStatement]);

        $statementAttribute = StatementAttributeFactory::createOne(['statement' => $testStatement]);

        $countiesOfStatement = $testStatement->getCounties();
        $municipalitiesOfStatement = $testStatement->getMunicipalities();
        $priorityAreasOfStatement = $testStatement->getPriorityAreas();
        $attributesOfStatement = $testStatement->getStatementAttributes();
        $totalAmountOfCounties = $this->countEntries(County::class);
        $totalAmountOfMunicipalities = $this->countEntries(Municipality::class);
        $totalAmountOfPriorityAreas = $this->countEntries(PriorityArea::class);
        $totalAmountOfStatementAttributes = $this->countEntries(StatementAttribute::class);

        static::assertNotEmpty($countiesOfStatement);
        static::assertNotEmpty($municipalitiesOfStatement);
        static::assertNotEmpty($priorityAreasOfStatement);
        static::assertNotEmpty($attributesOfStatement);
        static::assertInstanceOf(County::class, $countiesOfStatement[0]);
        static::assertInstanceOf(Municipality::class, $municipalitiesOfStatement[0]);
        static::assertInstanceOf(PriorityArea::class, $priorityAreasOfStatement[0]);
        static::assertInstanceOf(StatementAttribute::class, $attributesOfStatement[0]);

        // delete Statement
        $result = $this->sut->deleteStatementObject($testStatement->_real());
        static::assertTrue($result);

        // still the same of amount of counties/municipalities/priorityAreas in the DB
        static::assertSame(
            $totalAmountOfCounties,
            $this->countEntries(County::class)
        );
        static::assertSame(
            $totalAmountOfMunicipalities,
            $this->countEntries(Municipality::class)
        );
        static::assertSame(
            $totalAmountOfPriorityAreas,
            $this->countEntries(PriorityArea::class)
        );

        // exactly one StatementAttribute Entry is deleted via cascading:
        static::assertSame(
            $totalAmountOfStatementAttributes - 1,
            $this->countEntries(StatementAttribute::class)
        );
    }

    /**
     * deny deleting statement in case of currentUser != assignee
     * and allow deleting in case of no assignee or currentUser == assignee.
     */
    public function testDeleteAssignedStatement(): void
    {
        $this->enablePermissions(['feature_statement_assignment']);
        $currentUser = $this->loginTestUser();

        $testStatement2 = $this->getStatementReference('testStatement2');
        static::assertInstanceOf(StatementMeta::class, $testStatement2->getMeta());

        $assignee = $this->getUserReference('testUserPlanningOffice');
        $testStatement2->setAssignee($assignee);
        $this->statementService->updateStatementFromObject($testStatement2, true);

        $updatedStatement = $this->statementService->getStatement($testStatement2->getId());
        static::assertNotSame($updatedStatement->getAssignee()->getId(), $currentUser->getId());

        // this delete operation must fail due to an assignee on the statement
        $result = $this->sut->deleteStatementObject($updatedStatement);
        static::assertFalse($result);

        $testStatement2->setAssignee(null);
        $this->statementService->updateStatementFromObject($testStatement2, true);

        // this delete operation must succeed because there is no assignee anymore
        $result = $this->sut->deleteStatementObject($testStatement2);
        static::assertTrue($result);
    }

    public function testDeleteLockedStatement(): void
    {
        $this->enablePermissions(['feature_statement_assignment']);
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $statementId = $this->getStatementReference('testStatementAssigned6')->getId();
        $statement = $this->statementService->getStatement($statementId);
        static::assertEquals($user, $statement->getAssignee());

        $result = $this->sut->deleteStatementObject($statement);
        static::assertFalse($result);

        // Still there?
        static::assertInstanceOf(Statement::class, $this->statementService->getStatement($statementId));
        static::assertSame($statementId, $this->statementService->getStatement($statementId)->getId());
    }

    public function testDeleteUnLockedStatement(): void
    {
        $originalStatement = StatementFactory::createOne();
        $testStatement = StatementFactory::createOne(['original' => $originalStatement]);

        $this->enablePermissions(['feature_statement_assignment']);
        $statementId = $testStatement->getId();
        $statement = $this->statementService->getStatement($statementId);
        static::assertNull($statement->getAssignee());

        $result = $this->sut->deleteStatementObject($statement);
        static::assertTrue($result);

        static::assertNotInstanceOf(Statement::class, $this->statementService->getStatement($statementId));
        static::assertNull($this->statementService->getStatement($statementId));
    }

    /**
     * Deleting a statement which is the only child of the related original-statement while
     * "feature_auto_delete_original_statement" is enabled.
     * The related original-statement should also been deleted.
     */
    public function testCascadeDeleteOriginalStatement(): void
    {
        $testStatement = $this->getStatementReference('testStatementWithElementOnly');
        $testStatementId = $testStatement->getId();
        $testOriginalStatementId = $testStatement->getOriginal()->getId();
        $this->enablePermissions(['feature_auto_delete_original_statement']);

        self::assertFalse($testStatement->isOriginal());
        self::assertCount(1, $testStatement->getOriginal()->getChildren());
        $successful = $this->sut->deleteStatementObject($testStatement);
        self::assertTrue($successful);

        self::assertNull($this->find(Statement::class, $testStatementId));
        self::assertNull($this->find(Statement::class, $testOriginalStatementId));
    }

    /**
     * Deleting a statement which is the only child of the related original-statement while
     * "feature_auto_delete_original_statement" is disabled.
     * The related original-statement should be still existing.
     */
    public function testNotCascadeDeleteOriginalStatement()
    {
        $testStatement = $this->getStatementReference('testStatementWithElementOnly');
        $testStatementId = $testStatement->getId();
        $testOriginalStatementId = $testStatement->getOriginal()->getId();

        self::assertFalse($testStatement->isOriginal());
        self::assertCount(1, $testStatement->getOriginal()->getChildren());
        $successful = $this->sut->deleteStatementObject($testStatement);
        self::assertTrue($successful);

        self::assertNull($this->find(Statement::class, $testStatementId));
        self::assertInstanceOf(Statement::class, $this->find(Statement::class, $testOriginalStatementId));
    }

    /**
     * Deleting a statement which is one of multiple children of the related original-statement while
     * "feature_auto_delete_original_statement" is enabled.
     * The related original-statement should be still existing.
     */
    public function testNotCascadeDeleteOriginalStatement2()
    {
        $testStatement = $this->getStatementReference('normalStatement');
        $testStatementId = $testStatement->getId();
        $testOriginalStatementId = $testStatement->getOriginal()->getId();
        $this->enablePermissions(['feature_auto_delete_original_statement']);

        self::assertFalse($testStatement->isOriginal());
        self::assertGreaterThan(1, $testStatement->getOriginal()->getChildren()->count());
        $successful = $this->sut->deleteStatementObject($testStatement);
        self::assertTrue($successful);

        self::assertNull($this->find(Statement::class, $testStatementId));
        self::assertInstanceOf(Statement::class, $this->find(Statement::class, $testOriginalStatementId));
    }

    /**
     * T22439.
     *
     * Deleting a statement which is the only child of the related original-statement while
     * "feature_auto_delete_original_statement" is enabled.
     * The related original-statement should also been deleted.
     * The non-original statement, has segments, which also should been deleted.
     */
    public function testCascadeDeleteOriginalStatementWithSegments(): void
    {
        $ooo = StatementFactory::createOne();
        $testStatement = StatementFactory::createOne(['original' => $ooo]);
        $ooo->setChildren([$testStatement->object()]);

        $testSegment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $testStatement]);
        $testSegment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $testStatement]);

        //        $testStatement = $this->getStatementReference('statementTestTagsBulkEdit1');
        $testOriginalStatement = $testStatement->getOriginal();
        self::assertFalse($testStatement->isOriginal());
        self::assertNotNull($testOriginalStatement);
        self::assertNotEmpty($testOriginalStatement->getChildren());

        $testStatementId = $testStatement->getId();
        $testOriginalStatementId = $testStatement->getOriginal()->getId();
        $this->enablePermissions(['feature_auto_delete_original_statement']);

        // Segments are stored on non-original statement:
        self::assertNotEmpty($testStatement->getSegmentsOfStatement());

        // Expect exactly one children, to keep this testcase simple.
        self::assertCount(1, $testStatement->getOriginal()->getChildren());
        $successful = $this->sut->deleteStatementObject($testStatement->object());
        self::assertTrue($successful);

        // Use find() to search for IDs directly in DB to avoid doctrine cache
        self::assertNull($this->find(Statement::class, $testStatementId));
        self::assertNull($this->find(Statement::class, $testOriginalStatementId));
    }
}
