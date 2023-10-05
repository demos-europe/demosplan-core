<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Survey;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Validator\SurveyValidator;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

class SurveyHandler
{
    public function __construct(private readonly SurveyService $surveyService, private readonly array $surveyStatuses, private readonly ProcedureHandler $procedureHandler, private readonly SurveyValidator $surveyValidator)
    {
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
            if ('participation' === $status
                && 'participation' !== $procedure->getPublicParticipationPhase()) {
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
            : date('Y-m-d', strtotime((string) $surveyDataJson['startDate']));
        $surveyDataJson['startDate'] = $startDate;

        $procedureEndDate = $procedure->getPublicParticipationEndDate()->format('Y-m-d');
        $defaultDate = $procedureEndDate < $startDate
            ? $startDate
            : $procedureEndDate;

        $endDate = empty($surveyDataJson['endDate'])
            ? $defaultDate
            : date('Y-m-d', strtotime((string) $surveyDataJson['endDate']));
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
