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
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSurveyData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteHandler;
use Exception;
use Symfony\Bridge\Monolog\Logger;
use Tests\Base\UnitTestCase;

class SurveyTestUtils extends UnitTestCase
{
    /** @var string */
    protected $jsonSchemaPathCreate;

    /** @var string */
    protected $jsonSchemaPathUpdate;

    /** @var array */
    protected $expectedValidStatuses;

    /** @var Logger */
    protected $logger;

    /** @var DateTime */
    protected $surveyStartDate;

    /** @var DateTime */
    protected $surveyEndDate;

    /** @var string */
    protected $surveyStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new Logger('UnitTest');
        $this->jsonSchemaPathCreate = $this->getContainer()->getParameter(
            'survey.schema_path_create'
        );
        $this->jsonSchemaPathUpdate = $this->getContainer()->getParameter(
            'survey.schema_path_update'
        );
        $this->expectedValidStatuses = [
            'configuration', 'participation', 'evaluation', 'completed',
        ];
    }

    protected function checkSurveysEquals(Survey $s1, Survey $s2): void
    {
        $this->assertEquals($s1->getId(), $s2->getId());
        $this->assertEquals($s1->getTitle(), $s2->getTitle());
        $this->assertEquals($s1->getDescription(), $s2->getDescription());
        $this->assertEquals($s1->getStatus(), $s2->getStatus());
        $this->assertEquals($s1->getStartDate(), $s2->getStartDate());
        $this->assertEquals($s1->getEndDate(), $s2->getEndDate());
        $this->assertEquals($s1->getProcedure()->getId(), $s2->getProcedure()->getId());
    }

    protected function checkFrontendVotes(array $frontendVotes, Survey $survey): void
    {
        foreach ($frontendVotes as $frontendVote) {
            $this->checkFrontendVoteStructure($frontendVote);

            $voteId = $frontendVote['id'];
            $entityVote = $survey->getVote($voteId);
            $this->assertNotNull($entityVote);

            $this->checkFrontendVoteEqualsEntityVote($frontendVote, $entityVote);
        }
    }

    protected function checkFrontendVoteStructure(
        array $frontendVote,
    ): void {
        $this->assertCount(3, $frontendVote);
        $this->assertArrayHasKey('id', $frontendVote);
        $this->assertArrayHasKey('text', $frontendVote);
        $this->assertArrayHasKey('createdDate', $frontendVote);
    }

    protected function checkFrontendVoteEqualsEntityVote(
        array $frontendVote,
        SurveyVote $surveyVote,
    ): void {
        $this->assertEquals($frontendVote['id'], $surveyVote->getId());
        $this->assertEquals($frontendVote['text'], $surveyVote->getText());
        $this->assertEquals(
            $frontendVote['createdDate'],
            $surveyVote->getCreatedDate()->format(DateTime::ATOM)
        );
    }

    /**
     * Returns a valid json string.
     */
    protected function getValidInputJson(string $mode = 'update'): string
    {
        // uses local file, no need for flysystem
        $json = file_get_contents(__DIR__."/inputFiles/survey-input-$mode.json");
        $jsonArray = Json::decodeToArray($json);
        $jsonArray['procedureId'] = $this->getProcedureIdByReference('testProcedure');
        if ('update' === $mode) {
            /** @var Survey $survey */
            $survey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
            $jsonArray['surveyId'] = $survey->getId();
        }

        return Json::encode($jsonArray);
    }

    /**
     * Returns a valid json string.
     *
     * @throws Exception
     */
    protected function getValidInputSurvey(string $mode = 'update'): Survey
    {
        $surveyArray = $this->getValidInputArray($mode);
        $procedure = $this->getProcedureByReference('testProcedure');
        $survey = new Survey();
        $surveyId = $surveyArray['surveyId'] ?? '';
        $survey->setId($surveyId);
        $survey->setTitle($surveyArray['title']);
        $survey->setDescription($surveyArray['description']);
        $survey->setStartDate(new DateTime($surveyArray['startDate']));
        $survey->setEndDate(new DateTime($surveyArray['endDate']));
        $survey->setStatus($surveyArray['status']);
        $survey->setProcedure($procedure);

        return $survey;
    }

    /**
     * Returns an array with a valid input.
     */
    protected function getValidInputArray(string $mode = 'update'): array
    {
        return Json::decodeToArray($this->getValidInputJson($mode));
    }

    protected function getJsonSchemaFilePath(string $mode = 'update'): string
    {
        $jsonSchemaFilePath = 'create' === $mode
            ? $this->jsonSchemaPathCreate
            : $this->jsonSchemaPathUpdate;

        return $jsonSchemaFilePath;
    }

    protected function getProcedureByReference(string $reference): Procedure
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference($reference);

        return $procedure;
    }

    protected function getSurveyByReference(string $reference): Survey
    {
        /** @var Survey $survey */
        $survey = $this->fixtures->getReference($reference);

        return $survey;
    }

    protected function getProcedureIdByReference(string $reference): string
    {
        $procedure = $this->getProcedureByReference($reference);

        return null === $procedure ? '' : $procedure->getId();
    }

    /**
     * @throws Exception
     */
    protected function getSurveyInVotingPeriod(): Survey
    {
        /** @var Survey $survey */
        $survey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        $this->surveyStartDate = $survey->getStartDate();
        $this->surveyEndDate = $survey->getEndDate();
        $this->surveyStatus = $survey->getStatus();
        $survey->setStartDate(new DateTime('tomorrow - 5day'));
        $survey->setEndDate(new DateTime('tomorrow + 5day'));
        $survey->setStatus(Survey::STATUS_PARTICIPATION);

        return $survey;
    }

    protected function rollbackSurvey(): void
    {
        /** @var Survey $survey */
        $survey = $this->fixtures->getReference(LoadSurveyData::PARK_SURVEY);
        $survey->setStartDate($this->surveyStartDate);
        $survey->setEndDate($this->surveyEndDate);
        $survey->setStatus($this->surveyStatus);
    }

    /**
     * Mocks the necessary permissions for our tests.
     */
    protected function mockPermissions(): void
    {
        $permissionsMock = $this->createMock(PermissionsInterface::class);
        $permissionsMock->expects($this->once())
        ->method('hasPermission')
        ->with('feature_surveyvote_may_vote')
        ->willReturn(true);

        $surveyVoteHandler = self::getContainer()->get(SurveyVoteHandler::class);
        $surveyVoteHandler->setPermissions($permissionsMock);
    }
}
