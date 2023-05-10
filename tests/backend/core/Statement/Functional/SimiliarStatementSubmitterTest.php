<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\Base\FunctionalTestCase;

class SimiliarStatementSubmitterTest extends FunctionalTestCase
{
    /** @var StatementService System under Test */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser();
        $this->sut = $this->getContainer()->get(StatementService::class);
    }

    public function testAddSimilarStatementSubmitter(): void
    {
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testStatement1');
        $countStatementsBefore = $testProcedurePerson->getSimilarForeignStatements()->count();
        $countSubmittersBefore = $testStatement->getSimilarStatementSubmitters()->count();
        static::assertFalse($testProcedurePerson->getSimilarForeignStatements()->contains($testStatement));
        static::assertFalse($testStatement->getSimilarStatementSubmitters()->contains($testProcedurePerson));

        $testStatement->addSimilarStatementSubmitter($testProcedurePerson);

        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->find(Statement::class, $testStatement->getId());
        $testProcedurePerson = $this->find(ProcedurePerson::class, $testProcedurePerson->getId());

        static::assertCount($countStatementsBefore + 1, $testProcedurePerson->getSimilarForeignStatements());
        static::assertCount($countSubmittersBefore + 1, $testStatement->getSimilarStatementSubmitters());

        static::assertTrue($testProcedurePerson->getSimilarForeignStatements()->contains($testStatement));
        static::assertTrue($testStatement->getSimilarStatementSubmitters()->contains($testProcedurePerson));
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

        $deleted = $this->sut->deleteStatement($testStatementId);
        static::assertTrue($deleted);
        $testStatement = $this->find(Statement::class, $testStatementId);
        static::assertNull($testStatement);
    }

    /**
     * Cover deletion of related ProcedurePerson on deletion of a Statement.
     */
    public function testDeleteRelatedSubmitterOnDeleteStatement()
    {
        $testStatement = $this->getStatementReference('testFixtureStatement');
        $testStatementId = $testStatement->getId();
        $submitters = $testStatement->getSimilarStatementSubmitters();
        static::assertGreaterThan(0, $submitters->count());
        $relatedSubmittersIds =
            collect($submitters)->map(fn(ProcedurePerson $procedurePerson) => $procedurePerson->getId());

        $deleted = $this->sut->deleteStatement($testStatementId);
        static::assertTrue($deleted);
        $testStatement = $this->find(Statement::class, $testStatementId);
        static::assertNull($testStatement);

        //orphan removal deletes "detached" ProcedurePerson, even if another Statement is connected!
        foreach ($relatedSubmittersIds as $id) {
            static::assertNull($this->find(ProcedurePerson::class, $id));
        }
    }

    public function testAddSimilarForeignStatement(): void
    {
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $statementToAdd = $this->getStatementReference('testStatement1');
        $countStatementsBefore = $testProcedurePerson->getSimilarForeignStatements()->count();
        $countSubmittersBefore = $statementToAdd->getSimilarStatementSubmitters()->count();
        static::assertFalse($testProcedurePerson->getSimilarForeignStatements()->contains($statementToAdd));
        static::assertFalse($statementToAdd->getSimilarStatementSubmitters()->contains($testProcedurePerson));

        $testProcedurePerson->addSimilarForeignStatement($statementToAdd);
        $this->sut->updateStatementObject($statementToAdd);
        $statementToAdd = $this->find(Statement::class, $statementToAdd->getId());
        $testProcedurePerson = $this->find(ProcedurePerson::class, $testProcedurePerson->getId());

        static::assertCount($countStatementsBefore + 1, $testProcedurePerson->getSimilarForeignStatements());
        static::assertCount($countSubmittersBefore + 1, $statementToAdd->getSimilarStatementSubmitters());

        static::assertTrue($testProcedurePerson->getSimilarForeignStatements()->contains($statementToAdd));
        static::assertTrue($statementToAdd->getSimilarStatementSubmitters()->contains($testProcedurePerson));
    }

    /**
     * Orphan removal of related procedure persons will trigger in case of submitter will be removed
     * from related statement.
     *
     * @return void
     * @throws \Exception
     */
    public function testRemoveSimilarStatementSubmitter(): void
    {
        $testProcedurePerson1= $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testFixtureStatement');
        $similarSubmitters = $testStatement->getSimilarStatementSubmitters();
        $countSubmittersBefore = $similarSubmitters->count();
        static::assertContains($testProcedurePerson1, $testStatement->getSimilarStatementSubmitters());
        static::assertGreaterThan(0, $countSubmittersBefore);
        $relatedSubmittersIds =
            collect($similarSubmitters)->map(fn(ProcedurePerson $procedurePerson) => $procedurePerson->getId());

        $testStatement->removeSimilarStatementSubmitter($testProcedurePerson1);
        $this->sut->updateStatementObject($testStatement);

        //Caused by orphanRemoval = true, the related ProcedurePerson will be deleted.
        foreach ($relatedSubmittersIds as $similarSubmitterId) {
            static::assertNull($this->find(ProcedurePerson::class, $similarSubmitterId));
        }

        $testStatement = $this->find(Statement::class, $testStatement->getId());
        static::assertNotNull($testStatement);
        static::assertCount($countSubmittersBefore - 1, $testStatement->getSimilarStatementSubmitters());
        static::assertNotContains($testProcedurePerson1, $testStatement->getSimilarStatementSubmitters());
    }

    public function testSetEmptySimilarStatementSubmitters(): void
    {
        $testStatement = $this->getStatementReference('testFixtureStatement');
        $similarSubmitters = $testStatement->getSimilarStatementSubmitters();
        $countSubmittersBefore = $similarSubmitters->count();
        static::assertGreaterThan(0, $countSubmittersBefore);
        $relatedSubmittersIds =
            collect($similarSubmitters)->map(fn(ProcedurePerson $procedurePerson) => $procedurePerson->getId());

        //set empty collection to unset all related Submitters
        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([]));
        $this->sut->updateStatementObject($testStatement);

        foreach ($relatedSubmittersIds as $similarSubmitterId) {
            static::assertNull($this->find(ProcedurePerson::class, $similarSubmitterId));
        }

        $statementToAdd = $this->find(Statement::class, $testStatement->getId());
        static::assertNotNull($statementToAdd);
        static::assertCount($countSubmittersBefore - 1, $statementToAdd->getSimilarStatementSubmitters());
    }

    public function testSetSimilarStatementSubmitters(): void
    {
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testFixtureStatement');

        static::assertGreaterThan(1, $testStatement->getSimilarStatementSubmitters());
        static::assertGreaterThan(1, $testProcedurePerson->getSimilarForeignStatements());

        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([$testProcedurePerson]));
        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->sut->getStatement($testStatement->getId());

        static::assertCount(1, $testStatement->getSimilarStatementSubmitters());
        static::assertCount(1, $testProcedurePerson->getSimilarForeignStatements());
        static::assertContains($testStatement, $testProcedurePerson->getSimilarForeignStatements());
        static::assertContains($testProcedurePerson, $testStatement->getSimilarStatementSubmitters());
    }

    public function testRemoveSimilarStatementSubmitters(): void
    {
        //setup:
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        static::assertCount(1, $testProcedurePerson->getSimilarForeignStatements());






        $testStatement = $this->getStatementReference('testStatement1');
        static::assertNotNull($testStatement);
        static::assertNotNull($testProcedurePerson);

        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([$testProcedurePerson]));

        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->sut->getStatement($testStatement->getId());

        static::assertCount(2, $testProcedurePerson->getSimilarForeignStatements());

        //actual test
        $testProcedurePerson->removeSimilarForeignStatement($testStatement);
        $testStatement = $this->sut->updateStatementObject($testStatement);
        static::assertInstanceOf(Statement::class, $testStatement);

        $testStatement = $this->find(Statement::class, $testStatement->getId());
        $testProcedurePerson = $this->find(ProcedurePerson::class, $testProcedurePerson->getId());

        static::assertCount(0, $testStatement->getSimilarStatementSubmitters());
        static::assertCount(0, $testProcedurePerson->getSimilarForeignStatements());
    }

    public function testOrphanRemovalProcedurePerson(): void
    {
        //setup:
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testStatement');
        static::assertNotNull($testStatement);
        static::assertNotNull($testProcedurePerson);
        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([$testProcedurePerson]));
        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->sut->getStatement($testStatement->getId());

        //actual test
        $testProcedurePerson->removeSimilarForeignStatement($testStatement);
        $this->sut->updateStatementObject($testStatement);

        $testStatement = $this->find(Statement::class, $testStatement->getId());
        $testProcedurePersons = $this->getEntriesWhereInIds(ProcedurePerson::class, [$testProcedurePerson->getId()]);

        static::assertCount(0, $testStatement->getSimilarStatementSubmitters());
        static::assertEmpty($testProcedurePersons);
//        static::assertNull($testProcedurePerson);
    }

}
