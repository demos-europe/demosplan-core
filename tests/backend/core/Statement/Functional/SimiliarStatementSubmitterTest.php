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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\Base\FunctionalTestCase;

/**
 *
 */
class SimiliarStatementSubmitterTest extends FunctionalTestCase
{
    /** @var StatementService System under Test */
    protected $sut;

    //protected StatementService $statementService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(StatementService::class);
       // $this->statementService = $this->getContainer()->get(StatementService::class);
    }

    public function test123()
    {
        $this->loginTestUser();
        $testStatementWithToken1 = $this->getStatementReference('testStatement');
        $testProcedurePerson1 = $this->getProcedurePersonReference('testProcedurePerson1');
        static::assertInstanceOf(Statement::class, $testStatementWithToken1);
        static::assertInstanceOf(ProcedurePerson::class, $testProcedurePerson1);

        //$testSimilarStatementSubmitters = new ArrayCollection([$testProcedurePerson1]);
        //static::assertCount(0, $testStatementWithToken1->getSimilarStatementSubmitters());
       // $testStatementWithToken1->setSimilarStatementSubmitters($testSimilarStatementSubmitters);
       // $updatedStatement1 = $this->sut->updateStatementObject($testStatementWithToken1);
        //static::assertCount(1, $updatedStatement1->getSimilarStatementSubmitters());
        $deleted = $this->sut->deleteStatement($testStatementWithToken1->getId(), true);
        static::assertTrue($deleted);
    }
}
