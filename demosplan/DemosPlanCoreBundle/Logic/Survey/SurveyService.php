<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Survey;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\SurveyRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class SurveyService extends CoreService
{
    public function __construct(private readonly SurveyRepository $surveyRepository)
    {
    }

    public function findById(string $id): Survey
    {
        return $this->surveyRepository->find($id);
    }

    /**
     * @return array<Survey>
     */
    public function findAll(): array
    {
        return $this->surveyRepository->findAll();
    }

    /**
     * @param Survey $survey
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($survey): void
    {
        // Disclaimer, CoreRepository doesn't have an updateObject method
        $this->surveyRepository->updateObjects([$survey]);
    }

    /**
     * @param array<Statement> $surveys
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects(array $surveys): void
    {
        $this->surveyRepository->updateObjects($surveys);
    }

    public function generateSurveyStatistics(Procedure $procedure): array
    {
        $surveys = $procedure->getSurveys();
        $statistics = [];
        /** @var Survey $survey */
        foreach ($surveys as $survey) {
            $statistics['votes']['sum'] = count($survey->getVotes());

            // agreement
            $statistics['votes']['positive']['sum'] = count($survey->getPositiveVotes());
            $statistics['votes']['negative']['sum'] = count($survey->getNegativeVotes());

            // reviewRequired
            $statistics['votes']['reviewRequired']['sum'] = count($survey->getReviewRequiredVotes());

            // percent of published
            if (0 < $statistics['votes']['sum']) {
                $statistics['votes']['positive']['percent'] = round(
                    $statistics['votes']['positive']['sum'] / $statistics['votes']['sum'] * 100,
                    1
                );
                $statistics['votes']['negative']['percent'] = round(
                    $statistics['votes']['negative']['sum'] / $statistics['votes']['sum'] * 100,
                    1
                );
            }
        }

        return $statistics;
    }
}
