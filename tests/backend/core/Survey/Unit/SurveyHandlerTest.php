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
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyHandler;
use Exception;

class SurveyHandlerTest extends SurveyTestUtils
{
    /** @var SurveyHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyHandler::class);
    }

    /**
     * Test that a proper status array is delivered for frontend.
     */
    public function testGetSurveyStatusesArrayForEvaluationProcedure(): void
    {
        $this->checkGetSurveyStatusesArray('participation');
    }

    /**
     * Test that a proper status array is delivered for frontend.
     */
    public function testGetSurveyStatusesArrayForNonEvaluationProcedure(): void
    {
        $this->checkGetSurveyStatusesArray('configuration');
    }

    /**
     * Tests that the array send to FE is what it's expected based on the Procedure's phase.
     */
    private function checkGetSurveyStatusesArray(string $procedurePhase): void
    {
        $procedure = $this->getProcedureByReference('testProcedure');
        $procedure->setPublicParticipationPhase($procedurePhase);
        $expectedSurveyStatuses = [];
        $allConfiguredStatuses = $this->getContainer()->getParameter('survey.statuses');
        foreach ($allConfiguredStatuses as $status) {
            if ('participation' === $status &&
                'participation' !== $procedure->getPublicParticipationPhase()) {
                continue;
            }
            $expectedSurveyStatuses[] = $this->sut->getSurveyStatusArray($status);
        }
        $actualSurveyStatusesArray = $this->sut->getSurveyStatusesArray($procedure);
        $this->assertEquals($expectedSurveyStatuses, $actualSurveyStatusesArray);

        $expectedStatusesCount = 'participation' !== $procedurePhase
            ? count($allConfiguredStatuses) - 1
            : count($allConfiguredStatuses);
        $actualStatusesCount = count($actualSurveyStatusesArray);
        $this->assertEquals($expectedStatusesCount, $actualStatusesCount);
    }

    /**
     * Test that a Survey can be properly retrieved from its Procedure with the survey's id.
     *
     * @throws Exception
     */
    public function testGetProcedureSurvey(): void
    {
        $procedure = $this->getProcedureByReference('testProcedure');
        /** @var Survey $parkSurvey */
        $parkSurvey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        $procedureSchoolSurvey = $procedure->getSurvey($parkSurvey->getId());
        $this->checkSurveysEquals(
            $procedureSchoolSurvey,
            $this->sut->getProcedureSurvey($procedure->getId(), $parkSurvey->getId())
        );
    }
}
