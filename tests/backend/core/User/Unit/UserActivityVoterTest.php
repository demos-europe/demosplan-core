<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Authorization\Voter\UserActivityVoter;
use demosplan\DemosPlanCoreBundle\Logic\User\UserActivityInterface;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Tests\Base\UnitTestCase;

class UserActivityVoterTest extends UnitTestCase
{
    protected $sut;

    /** @var UserActivityInterface&MockObject */
    private $mockActivityChecker1;

    /** @var UserActivityInterface&MockObject */
    private $mockActivityChecker2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockActivityChecker1 = $this->createMock(UserActivityInterface::class);
        $this->mockActivityChecker2 = $this->createMock(UserActivityInterface::class);

        $this->sut = new UserActivityVoter([$this->mockActivityChecker1, $this->mockActivityChecker2]);
    }

    public function testVotesOnIsActiveUserAttribute(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->mockActivityChecker1->method('getPriority')->willReturn(100);
        $this->mockActivityChecker1->method('isUserActive')->with($user)->willReturn(true);

        $this->mockActivityChecker2->method('getPriority')->willReturn(50);

        // Act
        $result = $this->sut->vote($token, $user, [UserActivityVoter::IS_ACTIVE_USER]);

        // Assert
        $this->assertEquals(UserActivityVoter::ACCESS_GRANTED, $result);
    }

    public function testAbstainsOnOtherAttributes(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);

        // Act
        $result = $this->sut->vote($token, $user, ['OTHER_ATTRIBUTE']);

        // Assert
        $this->assertEquals(UserActivityVoter::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnNonUserSubjects(): void
    {
        // Arrange
        $nonUser = new stdClass();
        $token = $this->createMock(TokenInterface::class);

        // Act
        $result = $this->sut->vote($token, $nonUser, [UserActivityVoter::IS_ACTIVE_USER]);

        // Assert
        $this->assertEquals(UserActivityVoter::ACCESS_ABSTAIN, $result);
    }

    public function testVoteOnAttributeReturnsTrueWhenAnyCheckerReturnsTrue(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->mockActivityChecker1->method('getPriority')->willReturn(100);
        $this->mockActivityChecker1->method('isUserActive')->with($user)->willReturn(false);

        $this->mockActivityChecker2->method('getPriority')->willReturn(50);
        $this->mockActivityChecker2->method('isUserActive')->with($user)->willReturn(true);

        // Act
        $result = $this->sut->vote($token, $user, [UserActivityVoter::IS_ACTIVE_USER]);

        // Assert
        $this->assertEquals(UserActivityVoter::ACCESS_GRANTED, $result);
    }

    public function testVoteOnAttributeReturnsFalseWhenAllCheckersReturnFalse(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->mockActivityChecker1->method('getPriority')->willReturn(100);
        $this->mockActivityChecker1->method('isUserActive')->with($user)->willReturn(false);

        $this->mockActivityChecker2->method('getPriority')->willReturn(50);
        $this->mockActivityChecker2->method('isUserActive')->with($user)->willReturn(false);

        // Act
        $result = $this->sut->vote($token, $user, [UserActivityVoter::IS_ACTIVE_USER]);

        // Assert
        $this->assertEquals(UserActivityVoter::ACCESS_DENIED, $result);
    }

    public function testVoteOnAttributeReturnsTrueWhenNoCheckersConfigured(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $voterWithoutCheckers = new UserActivityVoter([]);

        // Act
        $result = $voterWithoutCheckers->vote($token, $user, [UserActivityVoter::IS_ACTIVE_USER]);

        // Assert
        $this->assertEquals(UserActivityVoter::ACCESS_GRANTED, $result);
    }

    public function testCheckersAreSortedByPriorityHighestFirst(): void
    {
        // Arrange
        $lowPriorityChecker = $this->createMock(UserActivityInterface::class);
        $highPriorityChecker = $this->createMock(UserActivityInterface::class);

        $lowPriorityChecker->method('getPriority')->willReturn(10);
        $highPriorityChecker->method('getPriority')->willReturn(100);

        $voter = new UserActivityVoter([$lowPriorityChecker, $highPriorityChecker]);

        // Act
        $checkers = $voter->getActivityCheckers();

        // Assert
        $this->assertSame($highPriorityChecker, $checkers[0]);
        $this->assertSame($lowPriorityChecker, $checkers[1]);
    }

    public function testAddActivityChecker(): void
    {
        // Arrange
        $newChecker = $this->createMock(UserActivityInterface::class);
        $newChecker->method('getPriority')->willReturn(200);

        // Act
        $this->sut->addActivityChecker($newChecker);
        $checkers = $this->sut->getActivityCheckers();

        // Assert
        $this->assertCount(3, $checkers);
        $this->assertSame($newChecker, $checkers[0]); // Should be first due to highest priority
    }
}
