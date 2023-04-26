<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Survey;

use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

class SurveyShowHandler
{
    /** @var SurveyVoteHandler */
    private $surveyVoteHandler;

    public function __construct(SurveyVoteHandler $surveyVoteHandler)
    {
        $this->surveyVoteHandler = $surveyVoteHandler;
    }

    /**
     * @param Survey|null $survey
     */
    public function entityToFrontend($survey, User $user): array
    {
        $result = [];

        if (null !== $survey && Survey::STATUS_CONFIGURATION !== $survey->getStatus()) {
            $result['id'] = $survey->getId();
            $result['title'] = $survey->getTitle();
            $result['description'] = $survey->getDescription();
            $result['startDate'] = $survey->getStartDate();
            $result['endDate'] = $survey->getEndDate();
            $result['status'] = $survey->getStatus();

            $result['votes'] = $this->surveyVoteHandler->getSurveyVotesInfo($survey);
            $result['userCanVote'] = $this->surveyVoteHandler->userCanVote($user, $survey);
        }

        return $result;
    }
}
