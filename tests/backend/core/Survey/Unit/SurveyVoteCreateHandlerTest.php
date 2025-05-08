<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteCreateHandler;
use Exception;

class SurveyVoteCreateHandlerTest extends SurveyVoteTestUtils
{
    /**
     * @var SurveyVoteCreateHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyVoteCreateHandler::class);
    }

    /**
     * @throws Exception
     */
    public function testCreateSurveyVote(): void
    {
        $this->mockPermissions();
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN10);
        /** @var Survey $user */
        $survey = $this->getSurveyInVotingPeriod();
        $isAgreed = true;
        $text = 'ich finde das gut.';
        $valid = true;
        try {
            $this->sut->getRequestSurveyVote(
                $isAgreed,
                $text,
                $user->getId(),
                $survey->getId()
            );
        } catch (InvalidArgumentException $e) {
            $valid = false;
        } catch (Exception $e) {
            echo 'Unexpected exception when creating a Survey';
        }
        $this->assertTrue($valid);
        $this->rollbackSurvey();
    }
}
