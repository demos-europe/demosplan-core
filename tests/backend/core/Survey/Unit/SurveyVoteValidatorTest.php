<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use DateTime;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Validator\SurveyValidator;
use demosplan\DemosPlanCoreBundle\Validator\SurveyVoteValidator;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;

class SurveyVoteValidatorTest extends SurveyVoteTestUtils
{
    /** @var SurveyVoteValidator */
    protected $sut;

    /** @var array */
    protected $validJsonArray;

    protected function setUp(): void
    {
        parent::setUp();
        /* @var SurveyValidator sut */
        $this->sut = self::$container->get(SurveyVoteValidator::class);

        $validJsonPath = __DIR__.'/inputFiles/surveyvote-input-create.json';
        // uses local file, no need for flysystem
        $validJson = file_get_contents($validJsonPath);
        $this->validJsonArray = Json::decodeToArray($validJson);
    }

    public function testValidJson(): void
    {
        $validJsonPath = __DIR__.'/inputFiles/surveyvote-input-create.json';
        // uses local file, no need for flysystem
        $validJson = file_get_contents($validJsonPath);
        $this->checkSchemaValidity($validJson, true, 'Json should be valid and it is not');
    }

    public function testJsonMissingIsAgreed(): void
    {
        $jsonArray = $this->validJsonArray;
        unset($jsonArray['data']['data']['attributes']['isAgreed']);
        $this->checkSchemaValidity(
            Json::encode($jsonArray),
            false,
            'Json has no isAgreed field but it is accepted');
    }

    public function testJsonMissingVoteText(): void
    {
        $jsonArray = $this->validJsonArray;
        unset($jsonArray['data']['data']['attributes']['text']);
        $this->checkSchemaValidity(
            Json::encode($jsonArray),
            false,
            'Json has no text field but it is accepted');
    }

    public function testJsonMissingUserRelationship(): void
    {
        $jsonArray = $this->validJsonArray;
        unset($jsonArray['data']['data']['relationships']['user']);
        $this->checkSchemaValidity(
            Json::encode($jsonArray),
            false,
            'Json has no survey relationship but it is accepted');
    }

    public function testJsonMissingSurveyRelationship(): void
    {
        $jsonArray = $this->validJsonArray;
        unset($jsonArray['data']['data']['relationships']['survey']);
        $this->checkSchemaValidity(
            Json::encode($jsonArray),
            false,
            'Json has no survey relationship but it is accepted');
    }

    /**
     * @throws Exception
     */
    public function testValidUser(): void
    {
        $this->mockPermissions();
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN10);
        $parkSurvey = $this->getSurveyInVotingPeriod();
        $isValid = true;
        try {
            $this->sut->userCanVote($parkSurvey, $user);
        } catch (InvalidArgumentException $e) {
            $isValid = false;
        }
        $this->assertTrue($isValid);
        $this->rollbackSurvey();
    }

    /**
     * @throws Exception
     */
    public function testUserAlreadyVoted(): void
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN2);
        $parkSurvey = $this->getSurveyInVotingPeriod();
        $isValid = true;
        try {
            $this->sut->userCanVote($parkSurvey, $user);
        } catch (InvalidArgumentException $e) {
            $isValid = false;
        }
        $this->assertFalse($isValid);
        $this->rollbackSurvey();
    }

    /**
     * @throws Exception
     */
    public function testValidSurvey(): void
    {
        $isValid = true;
        $parkSurvey = $this->getSurveyInVotingPeriod();
        try {
            $this->sut->surveyInVotingPeriod($parkSurvey);
        } catch (InvalidArgumentException $e) {
            $isValid = false;
        } catch (Exception $e) {
            echo 'wrong Exception thrown';
        }
        $this->assertTrue($isValid);
        $this->rollbackSurvey();
    }

    /**
     * @throws Exception
     */
    public function testSurveyNotYetStarted(): void
    {
        $isValid = true;
        $parkSurvey = $this->getSurveyInVotingPeriod();
        $parkSurvey->setStartDate(new DateTime('tomorrow'));
        try {
            $this->sut->surveyInVotingPeriod($parkSurvey);
        } catch (InvalidArgumentException $e) {
            $isValid = false;
        } catch (Exception $e) {
            echo 'wrong Exception thrown';
        }
        $this->assertFalse($isValid);
        $this->rollbackSurvey();
    }

    public function testSurveyAlreadyFinished(): void
    {
        try {
            $isValid = true;
            $parkSurvey = $this->getSurveyInVotingPeriod();
            $parkSurvey->setEndDate(new DateTime('today - 1day'));
            $this->sut->surveyInVotingPeriod($parkSurvey);
        } catch (InvalidArgumentException $e) {
            $isValid = false;
        } catch (Exception $e) {
            echo 'wrong Exception thrown';
        }
        $this->assertFalse($isValid);
        $this->rollbackSurvey();
    }

    public function testSurveyNotInParticipationStatus(): void
    {
        try {
            $isValid = true;
            /** @var Survey $parkSurvey */
            $parkSurvey = $this->getSurveyInVotingPeriod();
            $parkSurvey->setStatus(Survey::STATUS_CONFIGURATION);
            $this->sut->surveyInVotingPeriod($parkSurvey);
        } catch (InvalidArgumentException $e) {
            $isValid = false;
        } catch (Exception $e) {
            echo 'wrong Exception thrown';
        }
        $this->assertFalse($isValid);
        $this->rollbackSurvey();
    }

    /**
     * Check that a given json is validated as expected.
     */
    private function checkSchemaValidity(
        string $json,
        bool $mustBeValid,
        string $errorMsg,
    ): void {
        try {
            $validSchema = true;
            $this->sut->validateJson($json);
        } catch (InvalidSchemaException $e) {
            if ($mustBeValid) {
                $this->logger->error($e);
            }
            $validSchema = false;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $this->assertSame($validSchema, $mustBeValid, $errorMsg);
    }
}
