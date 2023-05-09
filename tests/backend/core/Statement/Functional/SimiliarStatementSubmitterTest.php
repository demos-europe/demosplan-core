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

    public function testAddSimilarForeignStatement(): void
    {
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $statementToAdd = $this->getStatementReference('testFixtureStatement');
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
     * To avoid triggering orphan removals, two related statements are required
     *
     * @return void
     * @throws \Exception
     */
    public function testRemoveSimilarStatementSubmitter(): void
    {
        $testProcedurePersonWithRelatedStatement = $this->getProcedurePersonReference('testProcedurePerson1');
        $statementWithRelatedSubmitter = $this->getStatementReference('testStatement');
        $countStatementsBefore = $testProcedurePersonWithRelatedStatement->getSimilarForeignStatements()->count();
        $countSubmittersBefore = $statementWithRelatedSubmitter->getSimilarStatementSubmitters()->count();
        static::assertTrue($testProcedurePersonWithRelatedStatement->getSimilarForeignStatements()->contains($statementWithRelatedSubmitter));
        static::assertTrue($statementWithRelatedSubmitter->getSimilarStatementSubmitters()->contains($testProcedurePersonWithRelatedStatement));
        static::assertGreaterThan(1, $countStatementsBefore);
        static::assertGreaterThan(0, $countSubmittersBefore);

        $statementWithRelatedSubmitter->removeSimilarStatementSubmitter($testProcedurePersonWithRelatedStatement);
        $this->sut->updateStatementObject($statementWithRelatedSubmitter);
        $statementToAdd = $this->find(Statement::class, $statementWithRelatedSubmitter->getId());
        $testProcedurePerson = $this->find(ProcedurePerson::class, $testProcedurePersonWithRelatedStatement->getId());

        static::assertCount($countStatementsBefore - 1, $testProcedurePerson->getSimilarForeignStatements());
        static::assertCount($countSubmittersBefore - 1, $statementToAdd->getSimilarStatementSubmitters());

        static::assertFalse($testProcedurePerson->getSimilarForeignStatements()->contains($statementToAdd));
        static::assertFalse($statementToAdd->getSimilarStatementSubmitters()->contains($testProcedurePerson));
    }

    public function testRemoveSimilarForeignStatement(): void
    {

    }

    public function testSetSimilarStatementSubmitters(): void
    {
        $testProcedurePerson = $this->getProcedurePersonReference('testProcedurePerson1');
        $testStatement = $this->getStatementReference('testStatement1');
        static::assertNotNull($testStatement);
        static::assertNotNull($testProcedurePerson);

        $testStatement->setSimilarStatementSubmitters(new ArrayCollection([$testProcedurePerson]));
        $this->sut->updateStatementObject($testStatement);
        $testStatement = $this->sut->getStatement($testStatement->getId());

        static::assertCount(1, $testStatement->getSimilarStatementSubmitters());
        static::assertSame($testStatement->getSimilarStatementSubmitters()[0]->getId(), $testProcedurePerson->getId() );
        static::assertCount(2, $testProcedurePerson->getSimilarForeignStatements());
        static::assertSame($testProcedurePerson->getSimilarForeignStatements()[0]->getId(), $testStatement->getId());
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

    public function testDoNotOrphanRemovalProcedurePerson(): void
    {
        //in case of more than one related statement on the procdure person,
        //the person should not be deleted
    }

    public function testDeleteStatementWithSimilarStatementSubmitters(): void
    {
    }

}
