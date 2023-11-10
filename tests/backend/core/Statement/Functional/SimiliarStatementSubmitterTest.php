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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
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
     * @throws Exception
     */
    public function testRemoveSimilarStatementSubmitter(): void
    {
        $testProcedurePerson1 = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testFixtureStatement');
        $testProcedurePerson1Id = $testProcedurePerson1->getId();
        $countSubmittersBefore = $testStatement->getSimilarStatementSubmitters()->count();
        static::assertContains($testProcedurePerson1, $testStatement->getSimilarStatementSubmitters());
        static::assertGreaterThan(0, $countSubmittersBefore);

        $testStatement->removeSimilarStatementSubmitter($testProcedurePerson1);
        $this->sut->updateStatementObject($testStatement);

        // Caused by orphanRemoval = true, the related ProcedurePerson will be deleted.
        static::assertNull($this->find(ProcedurePerson::class, $testProcedurePerson1Id));

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
            collect($similarSubmitters)->map(fn (ProcedurePerson $procedurePerson) => $procedurePerson->getId());

        // set empty collection to unset all related Submitters
        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([]));
        $this->sut->updateStatementObject($testStatement);

        foreach ($relatedSubmittersIds as $similarSubmitterId) {
            static::assertNull($this->find(ProcedurePerson::class, $similarSubmitterId));
        }

        $testStatement = $this->find(Statement::class, $testStatement->getId());
        static::assertNotNull($testStatement);
        static::assertCount(0, $testStatement->getSimilarStatementSubmitters());
    }

    public function testSetOneSimilarStatementSubmitter(): void
    {
        $testProcedurePersonToSet = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testFixtureStatement');

        $numberOfForeignStatementsBefore = $testProcedurePersonToSet->getSimilarForeignStatements()->count();
        $similarStatementSubmittersToSet = new ArrayCollection([$testProcedurePersonToSet]);
        $numberSimilarStatementSubmittersToSet = $similarStatementSubmittersToSet->count();
        $submitterDifference = $numberSimilarStatementSubmittersToSet - $testStatement->getSimilarStatementSubmitters()->count();
        $totalNumberOfProcedurePersonsBefore = $this->countEntries(ProcedurePerson::class);

        static::assertContains($testStatement, $testProcedurePersonToSet->getSimilarForeignStatements());
        // submitter to set is already set?
        static::assertContains($testProcedurePersonToSet, $testStatement->getSimilarStatementSubmitters());
        // greater than 1 to ensure the other should be deleted at the end.
        static::assertGreaterThan(1, $testStatement->getSimilarStatementSubmitters()->count());
        static::assertGreaterThan(1, $testProcedurePersonToSet->getSimilarForeignStatements()->count());

        $testStatement->setSimilarStatementSubmitters($similarStatementSubmittersToSet);
        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->sut->getStatement($testStatement->getId());

        static::assertCount($numberSimilarStatementSubmittersToSet, $testStatement->getSimilarStatementSubmitters());
        static::assertCount($numberOfForeignStatementsBefore, $testProcedurePersonToSet->getSimilarForeignStatements());
        static::assertContains($testStatement, $testProcedurePersonToSet->getSimilarForeignStatements());
        static::assertContains($testProcedurePersonToSet, $testStatement->getSimilarStatementSubmitters());

        // $submitterDifference should be -1 here
        static::assertSame($totalNumberOfProcedurePersonsBefore + $submitterDifference, $this->countEntries(ProcedurePerson::class));
    }

    public function testRemoveForeignStatement(): void
    {
        // setup:
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $testProcedurePersonId = $testProcedurePerson->getId();
        static::assertGreaterThan(0, $testProcedurePerson->getSimilarForeignStatements());
        /** @var Statement $testStatement */
        $testStatement = $testProcedurePerson->getSimilarForeignStatements()->first();
        $testStatementId = $testStatement->getId();
        $totalNumberOfProcedurePersonsBefore = $this->countEntries(ProcedurePerson::class);
        $totalNumberOfStatementsBefore = $this->countEntries(Statement::class);
        $numberOfRelatedProcedurePersonsBefore = $testStatement->getSimilarStatementSubmitters()->count();
        static::assertNotNull($testStatement);
        static::assertNotNull($testProcedurePerson);

        $testProcedurePerson->removeSimilarForeignStatement($testStatement);
        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->find(Statement::class, $testStatementId);

        static::assertNotNull($testStatement);
        static::assertNull($this->find(ProcedurePerson::class, $testProcedurePersonId));

        static::assertCount($numberOfRelatedProcedurePersonsBefore - 1, $testStatement->getSimilarStatementSubmitters());
        static::assertSame($totalNumberOfProcedurePersonsBefore - 1, $this->countEntries(ProcedurePerson::class));
        static::assertSame($totalNumberOfStatementsBefore, $this->countEntries(Statement::class));
    }

    public function testOrphanRemovalProcedurePerson(): void
    {
        // setup:
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testStatement');
        static::assertNotNull($testStatement);
        static::assertNotNull($testProcedurePerson);
        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([$testProcedurePerson]));
        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->sut->getStatement($testStatement->getId());

        // actual test
        $testProcedurePerson->removeSimilarForeignStatement($testStatement);
        $this->sut->updateStatementObject($testStatement);

        $testStatement = $this->find(Statement::class, $testStatement->getId());
        $testProcedurePersons = $this->getEntriesWhereInIds(ProcedurePerson::class, [$testProcedurePerson->getId()]);

        static::assertCount(0, $testStatement->getSimilarStatementSubmitters());
        static::assertEmpty($testProcedurePersons);
        //        static::assertNull($testProcedurePerson);
    }
}
