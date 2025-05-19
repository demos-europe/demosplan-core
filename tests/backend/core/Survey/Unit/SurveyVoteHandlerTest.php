<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSurveyData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteHandler;

class SurveyVoteHandlerTest extends SurveyVoteTestUtils
{
    /**
     * @var SurveyVoteHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyVoteHandler::class);
    }

    /**
     * Basic test for SurveyVote Entity and Repository.
     */
    public function testGetSurveyVoteById(): void
    {
        $surveyParkPositiveVote = $this->getSurveyParkVote();
        $surveyParkPositiveVoteDb = $this->sut->findById($surveyParkPositiveVote->getId());

        $this->checkSurveyVotesEquals($surveyParkPositiveVoteDb, $surveyParkPositiveVote);
    }

    /**
     * Test update a SurveyVote ().
     */
    public function testUpdateSurveyVote(): void
    {
        $surveyParkPositiveVote = $this->getSurveyParkVote();
        $surveyParkPositiveVote->setTextReview(SurveyVote::PUBLICATION_APPROVED);
        $surveyParkPositiveVoteDb = $this->sut->findById($surveyParkPositiveVote->getId());
        $this->assertTrue($surveyParkPositiveVoteDb->hasApprovedText());
    }

    /**
     * Test 1...N relationship between User and SurveyVotes.
     */
    public function testGetSurveyVotesByUser(): void
    {
        $surveyParkPositiveVote = $this->getSurveyParkVote();
        /** @var User $citizen */
        $citizen = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $citizenSurveyVotes = $citizen->getSurveyVote($surveyParkPositiveVote->getId());
        $this->checkSurveyVotesEquals($surveyParkPositiveVote, $citizenSurveyVotes);
    }

    /**
     * Test 1...N relationship between Survey and SurveyVotes.
     */
    public function testGetSurveyVotesBySurvey(): void
    {
        $surveyParkPositiveVote = $this->getSurveyParkVote();
        /** @var Survey $survey */
        $survey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        $citizenSurveyVotes = $survey->getVote($surveyParkPositiveVote->getId());
        $this->checkSurveyVotesEquals($surveyParkPositiveVote, $citizenSurveyVotes);
    }
}
