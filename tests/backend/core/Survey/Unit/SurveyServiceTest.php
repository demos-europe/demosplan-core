<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSurveyData;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyService;

class SurveyServiceTest extends SurveyTestUtils
{
    /**
     * @var SurveyService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyService::class);
    }

    /**
     * Basic test for Survey Entity and Repository.
     */
    public function testGetSurveyById(): void
    {
        /** @var Survey $parkSurvey */
        $parkSurvey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        $parkSurveyId = $parkSurvey->getId();

        $parkSurveyDb = $this->sut->findById($parkSurveyId);

        $this->checkSurveysEquals($parkSurveyDb, $parkSurvey);
    }

    /**
     * Test Procedure getter to retrieve a Survey with a given id.
     */
    public function testGetProcedureSurveyById(): void
    {
        $procedure = $this->getProcedureByReference('testProcedure');

        /** @var Survey $parkSurvey */
        $parkSurvey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        $procedureParkSurvey = $procedure->getSurvey($parkSurvey->getId());
        $this->assertNotNull($procedureParkSurvey);
        $this->checkSurveysEquals($procedureParkSurvey, $parkSurvey);

        /** @var Survey $poolSurvey */
        $poolSurvey = $this->fixtures->getReference(LoadSurveyData::POOL_SURVEY);
        $procedurePoolSurvey = $procedure->getSurvey($poolSurvey->getId());
        $this->assertNotNull($procedurePoolSurvey);
        $this->checkSurveysEquals($procedurePoolSurvey, $poolSurvey);
    }

    /**
     * Test 1...N relationship between Procedure and Survey.
     */
    public function testProcedureSurveys(): void
    {
        /** @var Survey $parkSurvey */
        $parkSurvey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        /** @var Survey $poolSurvey */
        $poolSurvey = $this->fixtures->getReference(LoadSurveyData::POOL_SURVEY);

        $procedure = $this->getProcedureByReference('testProcedure');
        $procedureSurveys = $procedure->getSurveys();
        $this->assertCount(2, $procedureSurveys);

        /** @var Survey $procedureSurvey */
        foreach ($procedureSurveys as $procedureSurvey) {
            if ($procedureSurvey->getId() === $parkSurvey->getId()) {
                $this->checkSurveysEquals($procedureSurvey, $parkSurvey);
            } else {
                $this->checkSurveysEquals($procedureSurvey, $poolSurvey);
            }
        }
    }
}
