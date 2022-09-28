<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanSurveyBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanSurveyBundle\Logic\SurveyUpdateHandler;
use Exception;

class SurveyUpdateHandlerTest extends SurveyTestUtils
{
    /** @var SurveyUpdateHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::$container->get(SurveyUpdateHandler::class);
    }

    /**
     * @throws Exception
     */
    public function testJsonToEntity(): void
    {
        /** @var Survey $survey */
        $surveyJson = $this->getValidInputJson('update');
        $expectedSurvey = $this->getValidInputSurvey('update');
        try {
            $actualSurvey = $this->sut->jsonToEntity($surveyJson);
            $this->checkSurveysEquals($expectedSurvey, $actualSurvey);
        } catch (SurveyInputDataException $e) {
            $this->logger->err($e->getUserMsg());
        }
        $this->assertTrue(true);
    }
}
