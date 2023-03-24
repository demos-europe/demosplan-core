<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanSurveyBundle\Logic;

use DateTime;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use demosplan\DemosPlanSurveyBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanSurveyBundle\Validator\SurveyValidator;
use Exception;

class SurveyCreateHandler
{
    /** @var string */
    private $schemaFilePath;

    /** @var SurveyValidator */
    private $surveyValidator;

    /** @var ProcedureHandler */
    private $procedureHandler;

    public function __construct(
        string $schemaFilePath,
        SurveyValidator $surveyValidator,
        ProcedureHandler $procedureHandler
    ) {
        $this->schemaFilePath = $schemaFilePath;
        $this->surveyValidator = $surveyValidator;
        $this->procedureHandler = $procedureHandler;
    }

    /**
     * @throws SurveyInputDataException
     * @throws Exception
     */
    public function jsonToEntity(string $jsonData): Survey
    {
        $this->surveyValidator->validSurveyData($jsonData, $this->schemaFilePath);
        $surveyDataArray = Json::decodeToArray($jsonData);
        $procedureId = $surveyDataArray['procedureId'];
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $survey = new Survey();
        $survey->setId('');
        $survey->setTitle($surveyDataArray['title']);
        $survey->setDescription($surveyDataArray['description']);
        $survey->setStartDate(new DateTime($surveyDataArray['startDate']));
        $survey->setEndDate(new DateTime($surveyDataArray['endDate']));
        $survey->setStatus($surveyDataArray['status']);
        $survey->setProcedure($procedure);

        return $survey;
    }
}
