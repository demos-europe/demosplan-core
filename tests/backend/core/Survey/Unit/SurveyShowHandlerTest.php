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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyShowHandler;
use Exception;

class SurveyShowHandlerTest extends SurveyTestUtils
{
    /** @var SurveyShowHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyShowHandler::class);
    }

    /**
     * When a Survey is in 'configuration' status shouldn't be publicly visible.
     * An empty array should be generated.
     */
    public function testSurveyInConfigurationStatusToFrontend(): void
    {
        $parkSurvey = $this->getSurveyByReference(LoadSurveyData::PARK_SURVEY);
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN2);
        $frontendEntity = $this->sut->entityToFrontend($parkSurvey, $user);

        $this->assertEmpty($frontendEntity);
    }

    /**
     * @throws Exception
     */
    public function testSurveyToFrontend(): void
    {
        $this->mockPermissions();
        $parkSurvey = $this->getSurveyInVotingPeriod();
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN10);
        $frontendEntity = $this->sut->entityToFrontend($parkSurvey, $user);

        $this->assertArrayHasKey('id', $frontendEntity);
        $this->assertEquals($frontendEntity['id'], $parkSurvey->getId());

        $this->assertArrayHasKey('title', $frontendEntity);
        $this->assertEquals($frontendEntity['id'], $parkSurvey->getId());

        $this->assertArrayHasKey('description', $frontendEntity);
        $this->assertEquals($frontendEntity['description'], $parkSurvey->getDescription());

        $this->assertArrayHasKey('startDate', $frontendEntity);
        $this->assertEquals($frontendEntity['startDate'], $parkSurvey->getStartDate());

        $this->assertArrayHasKey('endDate', $frontendEntity);
        $this->assertEquals($frontendEntity['endDate'], $parkSurvey->getEndDate());

        $this->assertArrayHasKey('status', $frontendEntity);
        $this->assertEquals($frontendEntity['status'], $parkSurvey->getStatus());

        $this->assertArrayHasKey('votes', $frontendEntity);

        $this->assertArrayHasKey('positiveVotes', $frontendEntity['votes']);
        $frontendPositiveVotes = $frontendEntity['votes']['positiveVotes'];
        $this->checkFrontendVotes($frontendPositiveVotes, $parkSurvey);

        $this->assertArrayHasKey('negativeVotes', $frontendEntity['votes']);
        $frontendNegativeVotes = $frontendEntity['votes']['negativeVotes'];
        $this->checkFrontendVotes($frontendNegativeVotes, $parkSurvey);

        $this->assertArrayHasKey('nPositive', $frontendEntity['votes']);
        $this->assertEquals(5, $frontendEntity['votes']['nPositive']);

        $this->assertArrayHasKey('nNegative', $frontendEntity['votes']);
        $this->assertEquals(4, $frontendEntity['votes']['nNegative']);

        $this->assertArrayHasKey('total', $frontendEntity['votes']);
        $this->assertEquals(9, $frontendEntity['votes']['total']);

        $this->assertArrayHasKey('percentagePositive', $frontendEntity['votes']);
        $this->assertEquals(
            $frontendEntity['votes']['percentagePositive'],
            round((5 / 9) * 100, 2)
        );

        $this->assertArrayHasKey('percentageNegative', $frontendEntity['votes']);
        $this->assertEquals(
            $frontendEntity['votes']['percentageNegative'],
            round((4 / 9) * 100, 2)
        );

        $this->assertArrayHasKey('userCanVote', $frontendEntity);
        $this->assertTrue($frontendEntity['userCanVote']);

        $this->rollbackSurvey();
    }

    public function testNullSurveyToFrontend(): void
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN10);
        $frontedEntity = $this->sut->entityToFrontend(null, $user);
        $this->assertEmpty($frontedEntity);

        $this->assertTrue(true);
    }
}
