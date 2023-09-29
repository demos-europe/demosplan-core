<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSurveyVoteData;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use Symfony\Bridge\Monolog\Logger;

class SurveyVoteTestUtils extends SurveyTestUtils
{
    /** @var Logger */
    protected $logger;

    protected function checkSurveyVotesEquals(SurveyVote $sv1, SurveyVote $sv2): void
    {
        $this->assertEquals($sv1->getId(), $sv2->getId());
        $this->assertEquals($sv1->isAgreed(), $sv2->isAgreed());
        $this->assertEquals($sv1->hasApprovedText(), $sv2->hasApprovedText());
        $this->assertEquals($sv1->getCreatedDate(), $sv2->getCreatedDate());
        $this->assertEquals($sv1->getText(), $sv2->getText());
        $this->assertEquals($sv1->getUser()->getId(), $sv2->getUser()->getId());
        $this->assertEquals($sv1->getSurvey()->getId(), $sv2->getSurvey()->getId());
    }

    protected function getSurveyParkVote(): SurveyVote
    {
        $surveyVoteReference = LoadSurveyVoteData::SURVEY_PARK_POSITIVE1;
        /** @var SurveyVote $surveyParkPositiveVote */
        $surveyParkPositiveVote = $this->fixtures->getReference($surveyVoteReference);

        return $surveyParkPositiveVote;
    }

    protected function getSurveyParkVoteId(): string
    {
        $surveyVoteReference = LoadSurveyVoteData::SURVEY_PARK_POSITIVE1;
        /** @var SurveyVote $surveyParkPositiveVote */
        $surveyParkPositiveVote = $this->fixtures->getReference($surveyVoteReference);

        return $surveyParkPositiveVote->getId();
    }
}
