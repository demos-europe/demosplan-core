<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureStatisticsService;
use Tests\Base\FunctionalTestCase;

class ProcedureStatisticsServiceTest extends FunctionalTestCase
{
    /** @var ProcedureStatisticsService */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ProcedureStatisticsService::class);
    }

    /**
     * Test to check if the test setup works at all.
     */
    public function testTrue(): void
    {
        self::assertTrue(true);
    }

    public function testStatementGet(): void
    {
        self::markSkippedForCIIntervention();

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_json_api_statement',
            'feature_json_api_statement_segment',
            // the following two should not be needed
            // but are anyway for internal reasons currently
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);

        $expected = $this->getStatementReference('testStatement');
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        $percentageDistribution = $this->sut->getSegmentedStatementsDistribution($expected->getProcedure()->getId());

        self::assertSame(22, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        self::assertSame(22, $absolutes['unsegmented']);
        self::assertSame(0, $absolutes['segmented']);
        self::assertSame(0, $absolutes['recommendationsFinished']);
    }
}
