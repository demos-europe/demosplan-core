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
use demosplan\DemosPlanCoreBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyCreateHandler;
use Exception;

class SurveyCreateHandlerTest extends SurveyTestUtils
{
    /** @var SurveyCreateHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyCreateHandler::class);
    }

    /**
     * @throws Exception
     */
    public function testJsonToEntity(): void
    {
        /** @var Survey $survey */
        $surveyJson = $this->getValidInputJson('create');
        $expectedSurvey = $this->getValidInputSurvey('create');
        try {
            $actualSurvey = $this->sut->jsonToEntity($surveyJson);
            $this->checkSurveysEquals($expectedSurvey, $actualSurvey);
        } catch (SurveyInputDataException $e) {
            $this->logger->error($e->getUserMsg());
        }
        $this->assertTrue(true);
    }
}
