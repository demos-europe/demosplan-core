<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyNewHandler;
use Exception;

class SurveyNewHandlerTest extends SurveyTestUtils
{
    /** @var SurveyNewHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyNewHandler::class);
    }

    /**
     * @throws Exception
     */
    public function testDefaultSurvey(): void
    {
        $procedure = $this->getProcedureByReference('testProcedure');
        /** @var Survey $surveyDefaults */
        $surveyDefaults = $this->sut->getSurveyDefaults($procedure);
        $this->assertEmpty($surveyDefaults->getId());
        $this->assertEmpty($surveyDefaults->getTitle());
        $this->assertEmpty($surveyDefaults->getDescription());
        $this->assertEquals(date('Y-m-d'), $surveyDefaults->getStartDate()->format('Y-m-d'));
        $this->assertEquals(
            $procedure->getPublicParticipationEndDate()->format('Y-m-d'),
            $surveyDefaults->getEndDate()->format('Y-m-d')
        );
        $surveyDefaultStatus = $this->getContainer()->getParameter('survey.status.default');
        $this->assertEquals(
            $surveyDefaultStatus,
            $surveyDefaults->getStatus()
        );
        $this->assertEquals($procedure->getId(), $surveyDefaults->getProcedure()->getId());
    }
}
