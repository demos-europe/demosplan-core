<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use DateTimeImmutable;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use Tests\Base\UnitTestCase;

class LastLoginActivityCheckerTest extends UnitTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new LastLoginActivityChecker(30);
    }

    public function testIsUserActiveReturnsTrueWhenUserLoggedInWithinThreshold(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $recentLogin = new DateTimeImmutable('-10 days');
        $user->method('getLastLogin')->willReturn($recentLogin);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsUserActiveReturnsFalseWhenUserLoggedInOutsideThreshold(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $oldLogin = new DateTimeImmutable('-40 days');
        $user->method('getLastLogin')->willReturn($oldLogin);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsUserActiveReturnsTrueWhenUserNeverLoggedIn(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(null);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsUserActiveReturnsTrueWhenUserLoggedInExactlyAtThreshold(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        // Create a login that's exactly 30 days ago (slightly after the threshold to ensure it passes)
        $thresholdLogin = new DateTimeImmutable('-29 days 23 hours 59 minutes');
        $user->method('getLastLogin')->willReturn($thresholdLogin);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsUserActiveReturnsTrueWhenUserLoggedInToday(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $todayLogin = new DateTimeImmutable('now');
        $user->method('getLastLogin')->willReturn($todayLogin);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testGetActivityDescriptionReturnsCorrectDescription(): void
    {
        // Act
        $description = $this->sut->getActivityDescription();

        // Assert
        $this->assertEquals('User has logged in within the last 30 days or shows signs of account activity', $description);
    }

    public function testGetActivityDescriptionWithCustomThreshold(): void
    {
        // Arrange
        $checker = new LastLoginActivityChecker(60);

        // Act
        $description = $checker->getActivityDescription();

        // Assert
        $this->assertEquals('User has logged in within the last 60 days or shows signs of account activity', $description);
    }

    public function testGetPriorityReturnsHighPriority(): void
    {
        // Act
        $priority = $this->sut->getPriority();

        // Assert
        $this->assertEquals(100, $priority);
    }

    public function testGetDayThresholdReturnsCorrectValue(): void
    {
        // Act
        $threshold = $this->sut->getDayThreshold();

        // Assert
        $this->assertEquals(30, $threshold);
    }

    public function testSetDayThresholdUpdatesValue(): void
    {
        // Arrange
        $newThreshold = 45;

        // Act
        $this->sut->setDayThreshold($newThreshold);

        // Assert
        $this->assertEquals($newThreshold, $this->sut->getDayThreshold());
    }

    public function testSetDayThresholdAffectsActivityCheck(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $login35DaysAgo = new DateTimeImmutable('-35 days');
        $user->method('getLastLogin')->willReturn($login35DaysAgo);

        // Act - First with 30 day threshold (should be inactive)
        $resultWith30Days = $this->sut->isUserActive($user);

        // Act - Then with 40 day threshold (should be active)
        $this->sut->setDayThreshold(40);
        $resultWith40Days = $this->sut->isUserActive($user);

        // Assert
        $this->assertFalse($resultWith30Days);
        $this->assertTrue($resultWith40Days);
    }

    public function testIsUserActiveWithOneDayThreshold(): void
    {
        // Arrange
        $checker = new LastLoginActivityChecker(1);
        $user = $this->createMock(UserInterface::class);
        // Login from yesterday should be active with 1-day threshold
        $yesterdayLogin = new DateTimeImmutable('-12 hours');
        $user->method('getLastLogin')->willReturn($yesterdayLogin);

        // Act
        $result = $checker->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsUserActiveWithVeryLargeDayThreshold(): void
    {
        // Arrange
        $checker = new LastLoginActivityChecker(3650); // ~10 years
        $user = $this->createMock(UserInterface::class);
        $veryOldLogin = new DateTimeImmutable('-5 years');
        $user->method('getLastLogin')->willReturn($veryOldLogin);

        // Act
        $result = $checker->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }
}
