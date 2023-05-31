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
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class SurveyVoteHandler
{
    /** @var SurveyVoteService */
    private $surveyVoteService;

    /** @var PermissionsInterface */
    private $permissions;

    public function __construct(
        SurveyVoteService $surveyVoteService,
        PermissionsInterface $permissions
    ) {
        $this->permissions = $permissions;
        $this->surveyVoteService = $surveyVoteService;
    }

    public function findById(string $id): ?SurveyVote
    {
        return $this->surveyVoteService->findById($id);
    }

    /**
     * @return array<SurveyVote>
     */
    public function findAll(): array
    {
        return $this->surveyVoteService->findAll();
    }

    /**
     * @param SurveyVote $surveyVote
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($surveyVote): void
    {
        $this->surveyVoteService->updateObject($surveyVote);
    }

    /**
     * @param array<SurveyVote> $surveyVotes
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects(array $surveyVotes): void
    {
        $this->surveyVoteService->updateObjects($surveyVotes);
    }

    /**
     * Returns true if the user has not yet voted in the Survey.
     */
    public function userCanVote(User $user, Survey $survey): bool
    {
        if (!$this->permissions->hasPermission('feature_surveyvote_may_vote')) {
            return false;
        }
        if (Survey::STATUS_PARTICIPATION !== $survey->getStatus()) {
            return false;
        }

        $surveyId = $survey->getId();
        $userVotes = $user->getSurveyVotes();
        $userSurveyVotes = $userVotes->filter(
            static function (SurveyVote $vote) use ($surveyId) {
                return $surveyId === $vote->getSurvey()->getId();
            }
        );

        return 0 === $userSurveyVotes->count();
    }

    public function getSurveyVotesInfo(Survey $survey, bool $onlyNumbers = false): array
    {
        $positiveVotes = $survey->getPositiveVotes();
        $negativeVotes = $survey->getNegativeVotes();
        $nPositive = $positiveVotes->count();
        $nNegative = $negativeVotes->count();
        $total = $nPositive + $nNegative;
        if (!$onlyNumbers) {
            $votesFrontend['positiveVotes'] = $this->surveyVotesToFrontend($positiveVotes);
            $votesFrontend['negativeVotes'] = $this->surveyVotesToFrontend($negativeVotes);
        }
        $votesFrontend['nPositive'] = $nPositive;
        $votesFrontend['nNegative'] = $nNegative;
        $votesFrontend['total'] = $nPositive + $nNegative;
        $votesFrontend['percentagePositive'] = 0 === $total
            ? 0
            : round(($nPositive / $total) * 100, 2);
        $votesFrontend['percentageNegative'] = 0 === $total
            ? 0
            : round(($nNegative / $total) * 100, 2);

        return $votesFrontend;
    }

    private function surveyVotesToFrontend(Collection $surveyVotes): array
    {
        $result = [];
        /** @var SurveyVote $surveyVote */
        foreach ($surveyVotes as $surveyVote) {
            if ($surveyVote->hasApprovedText()) {
                $result[] = $this->surveyVoteToFrontend($surveyVote);
            }
        }

        return $result;
    }

    private function surveyVoteToFrontend(SurveyVote $surveyVote): array
    {
        $result['id'] = $surveyVote->getId();
        $result['text'] = $surveyVote->getText();
        $result['createdDate'] = $surveyVote->getCreatedDate()->format(DateTime::ATOM);

        return $result;
    }

    public function setPermissions(PermissionsInterface $permissions): void
    {
        $this->permissions = $permissions;
    }
}
