<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNameTooLongException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceStorage;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Tests\Base\FunctionalTestCase;

class AssessmentTableServiceStorageTest extends FunctionalTestCase
{
    /**
     * @var AssessmentTableServiceStorage
     */
    protected $sut;

    /**
     * Make it impossible to update (cluster) statements with a name considered too long.
     *
     * The current implementation throws an StatementNameTooLongException, but you may adjust this test as
     * you like (eg. when the validation is refactored to use symfony validation) as long as the name length check
     * is tested here.
     *
     * @throws MessageBagException
     */
    public function testStartServiceActionStatementNameTooLongException()
    {
        $clusterStatement = $this->getStatementReference('clusterStatement 1');
        $clusterStatementId = $clusterStatement->getId();

        $this->expectException(StatementNameTooLongException::class);
        $rParams = [
            'request' => [
                'clusterName' => str_repeat('x', 201),
                'action'      => 'update',
                'ident'       => $clusterStatementId,
            ],
        ];
        $this->sut->executeAdditionalSingleViewAction($rParams);
        self::fail('expected specific exception');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(AssessmentTableServiceStorage::class);

        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
    }
}
