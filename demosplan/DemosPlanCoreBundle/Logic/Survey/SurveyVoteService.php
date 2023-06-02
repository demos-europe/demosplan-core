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
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\SurveyVoteRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use UnexpectedValueException;

class SurveyVoteService extends CoreService
{
    /**
     * @var SurveyVoteRepository
     */
    private $surveyVoteRepository;

    public function __construct(SurveyVoteRepository $surveyVoteRepository)
    {
        $this->surveyVoteRepository = $surveyVoteRepository;
    }

    public function findById(string $id): SurveyVote
    {
        /** @var SurveyVote $surveyVote */
        $surveyVote = $this->surveyVoteRepository->find($id);

        if (null === $surveyVote) {
            throw new UnexpectedValueException(sprintf('SurveyVote with the following Id not found: %s', $id));
        }

        return $surveyVote;
    }

    /**
     * @return array<SurveyVote>
     */
    public function findAll(): array
    {
        return $this->surveyVoteRepository->findAll();
    }

    /**
     * Get list of all SurveyVotes of a given procedure.
     *
     * @return array<SurveyVote>
     */
    public function findByProcedure(Procedure $procedure): array
    {
        return $this->surveyVoteRepository->findByProcedure($procedure);
    }

    /**
     * @param SurveyVote $surveyVote
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($surveyVote): void
    {
        $this->surveyVoteRepository->updateObjects([$surveyVote]);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects(array $surveyVotes): void
    {
        $this->surveyVoteRepository->updateObjects($surveyVotes);
    }
}
