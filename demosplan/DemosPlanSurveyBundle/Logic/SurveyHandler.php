<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanSurveyBundle\Logic;

use DemosEurope\DemosplanAddon\Utilities\Json;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use demosplan\DemosPlanSurveyBundle\Validator\SurveyValidator;

class SurveyHandler
{
    /** @var SurveyService */
    private $surveyService;

    /** @var array */
    private $surveyStatuses;

    /** @var ProcedureHandler */
    private $procedureHandler;

    /** @var SurveyValidator */
    private $surveyValidator;

    public function __construct(SurveyService $surveyService, array $surveyStatuses, ProcedureHandler $procedureHandler, SurveyValidator $surveyValidator)
    {
        $this->surveyService = $surveyService;
        $this->surveyStatuses = $surveyStatuses;
        $this->procedureHandler = $procedureHandler;
        $this->surveyValidator = $surveyValidator;
    }

    public function findById(string $id): ?Survey
    {
        return $this->surveyService->findById($id);
    }

    /**
     * @return array<Survey>
     */
    public function findAll(): array
    {
        return $this->surveyService->findAll();
    }

    /**
     * @param Survey $survey
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($survey): void
    {
        $this->surveyService->updateObject($survey);
    }

    /**
     * @param array<Statement> $surveys
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects(array $surveys): void
    {
        $this->surveyService->updateObjects($surveys);
    }

    public function getSurveyStatusesArray(Procedure $procedure): array
    {
        $result = [];
        foreach ($this->surveyStatuses as $status) {
            if ('participation' === $status &&
                'participation' !== $procedure->getPublicParticipationPhase()) {
                continue;
            }
            $result[] = $this->getSurveyStatusArray($status);
        }

        return $result;
    }

    public function getSurveyStatusArray(string $status): array
    {
        return [
            'value' => $status,
            'label' => 'survey.status.'.$status,
        ];
    }

    /**
     * @throws Exception
     */
    public function getSurveyJsonData(
        string $procedureId,
        array $surveyDataJson,
        string $surveyId = ''
    ): string {
        $this->surveyValidator->procedureExists($procedureId);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);

        $startDate = empty($surveyDataJson['startDate'])
            ? date('Y-m-d')
            : date('Y-m-d', strtotime($surveyDataJson['startDate']));
        $surveyDataJson['startDate'] = $startDate;

        $procedureEndDate = $procedure->getPublicParticipationEndDate()->format('Y-m-d');
        $defaultDate = $procedureEndDate < $startDate
            ? $startDate
            : $procedureEndDate;

        $endDate = empty($surveyDataJson['endDate'])
            ? $defaultDate
            : date('Y-m-d', strtotime($surveyDataJson['endDate']));
        $surveyDataJson['endDate'] = $endDate;

        $surveyDataJson['surveyId'] = $surveyId;
        $surveyDataJson['procedureId'] = $procedureId;

        return Json::encode($surveyDataJson);
    }

    /**
     * @throws Exception
     */
    public function getProcedureSurvey(string $procedureId, string $surveyId): Survey
    {
        $this->surveyValidator->procedureExists($procedureId);
        $this->surveyValidator->surveyBelongsToProcedure($procedureId, $surveyId);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);

        return $procedure->getSurvey($surveyId);
    }

    /**
     * @throws Exception
     */
    public function getProcedureSurveys(string $procedureId): Collection
    {
        $this->surveyValidator->procedureExists($procedureId);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);

        return $procedure->getSurveys();
    }
}
