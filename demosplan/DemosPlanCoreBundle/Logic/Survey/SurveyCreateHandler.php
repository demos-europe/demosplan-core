<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Survey;

use DateTime;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Validator\SurveyValidator;
use Exception;

class SurveyCreateHandler
{
    public function __construct(private readonly string $schemaFilePath, private readonly SurveyValidator $surveyValidator, private readonly ProcedureHandler $procedureHandler)
    {
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
