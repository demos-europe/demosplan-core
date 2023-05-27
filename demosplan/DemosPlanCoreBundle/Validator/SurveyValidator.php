<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use Exception;
use InvalidArgumentException;
use JsonSchema\Exception\InvalidSchemaException;

class SurveyValidator
{
    /** @var ProcedureHandler */
    private $procedureHandler;

    /** @var JsonSchemaValidator */
    private $jsonSchemaValidator;

    public function __construct(
        ProcedureHandler $procedureHandler,
        JsonSchemaValidator $jsonSchemaValidator
    ) {
        $this->procedureHandler = $procedureHandler;
        $this->jsonSchemaValidator = $jsonSchemaValidator;
    }

    /**
     * Validates that a Procedure with given Id exists.
     * Otherwise throws a SurveyInputDataException.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function procedureExists(string $procedureId): Procedure
    {
        $procedure = $this->procedureHandler->getProcedure($procedureId);
        if (null === $procedure) {
            throw new InvalidArgumentException("No Procedure with id: '$procedureId' found.", SurveyInputDataException::NONEXISTENT_PROCEDURE);
        }

        return $procedure;
    }

    /**
     * Validates that the json with the Survey data fits to the expected schema, throwing an
     * InvalidSchemaException if it doesn't.
     *
     * If there are inconsistencies in the data (endDate previous startDate) throws a
     * SurveyInputDataException.
     *
     * @throws SurveyInputDataException
     * @throws InvalidSchemaException
     * @throws Exception
     */
    public function validSurveyData(string $surveyData, string $schemaFilePath): void
    {
        $this->jsonSchemaValidator->validate($surveyData, $schemaFilePath);
        $surveyDataArray = Json::decodeToArray($surveyData);
        $procedureId = $surveyDataArray['procedureId'];
        $this->procedureExists($procedureId);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        if (Survey::STATUS_PARTICIPATION === $surveyDataArray['status'] &&
            'participation' !== $procedure->getPublicParticipationPhase()) {
            throw new SurveyInputDataException('error.status.evaluation', 'Survey was send with "participation" status whereas Procedure\'s status is '.$procedure->getPhase(), SurveyInputDataException::SURVEY_EVALUATION_IN_WRONG_PROCEDURE_STATUS);
        }
        $this->validateSurveyDates($procedure, $surveyDataArray);
    }

    /**
     * @throws SurveyInputDataException
     */
    public function validateSurveyDates(Procedure $procedure, array $surveyDataArray): void
    {
        if ('participation' === $surveyDataArray['status'] &&
            'participation' === $procedure->getPublicParticipationPhase()
        ) {
            if (empty($surveyDataArray['startDate'])) {
                throw new SurveyInputDataException('error.startdate.required', 'No start date received for a survey n evaluation phase', SurveyInputDataException::MISSING_START_DATE);
            }
            if (empty($surveyDataArray['endDate'])) {
                throw new SurveyInputDataException('error.enddate.required', 'No end date received for a survey in evaluation phase', SurveyInputDataException::MISSING_END_DATE);
            }
            $endDate = $surveyDataArray['endDate'];
            $startDate = $surveyDataArray['startDate'];
            if ($startDate > $endDate) {
                throw new SurveyInputDataException('error.date.endbeforestart', 'Survey start date after Survey end date', SurveyInputDataException::START_DATE_AFTER_END_DATE);
            }
            $procedureEndDate = $procedure->getPublicParticipationEndDate()->format('Y-m-d');
            if ($surveyDataArray['endDate'] > $procedureEndDate) {
                throw new SurveyInputDataException('survey.error.survey.enddate.after.procedure.enddate', 'Survey end date after Procedure end date', SurveyInputDataException::END_DATE_AFTER_END_PROCEDURE);
            }
        }
    }

    /**
     * Validates that the Survey identified by $surveyId belongs to Procedure identified by
     * $procedure. If it doesn't a SurveyInputDataException is thrown.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function surveyBelongsToProcedure(string $procedureId, string $surveyId): void
    {
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $survey = $procedure->getSurvey($surveyId);
        if (null === $survey) {
            throw new InvalidArgumentException('Procedure with id "'.$procedureId.'" does not have a survey with id "'.$surveyId.'"', SurveyInputDataException::SURVEY_NOT_IN_PROCEDURE);
        }
    }
}
