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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\User\ClaimedStatementsActivityChecker;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Base\UnitTestCase;

class ClaimedStatementsActivityCheckerTest extends UnitTestCase
{
    protected $sut;

    /** @var StatementRepository&MockObject */
    private $mockStatementRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStatementRepository = $this->createMock(StatementRepository::class);
        $this->sut = new ClaimedStatementsActivityChecker($this->mockStatementRepository, 90);
    }

    public function testIsUserActiveReturnsFalseWhenUserHasNoClaimedStatements(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([]);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsUserActiveReturnsTrueWhenUserHasRecentlyModifiedClaimedStatements(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $statement = $this->createMock(Statement::class);
        $recentModification = new DateTimeImmutable('-10 days');

        $statement->method('getModified')->willReturn($recentModification);

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$statement]);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsUserActiveReturnsFalseWhenUserHasOnlyOldClaimedStatements(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $statement = $this->createMock(Statement::class);
        $oldModification = new DateTimeImmutable('-100 days');

        $statement->method('getModified')->willReturn($oldModification);

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$statement]);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsUserActiveReturnsTrueWhenOneOfMultipleStatementsIsRecentlyModified(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $oldStatement = $this->createMock(Statement::class);
        $recentStatement = $this->createMock(Statement::class);

        $oldStatement->method('getModified')->willReturn(new DateTimeImmutable('-100 days'));
        $recentStatement->method('getModified')->willReturn(new DateTimeImmutable('-10 days'));

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$oldStatement, $recentStatement]);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsUserActiveReturnsFalseWhenStatementHasNullModifiedDate(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $statement = $this->createMock(Statement::class);

        $statement->method('getModified')->willReturn(null);

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$statement]);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsUserActiveReturnsTrueWhenStatementModifiedNearThreshold(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $statement = $this->createMock(Statement::class);
        // Create a modification that's just within the threshold
        $nearThresholdModification = new DateTimeImmutable('-89 days 23 hours 59 minutes');

        $statement->method('getModified')->willReturn($nearThresholdModification);

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$statement]);

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
        $this->assertEquals(
            'User has claimed statements or segments with activity within the last 90 days',
            $description
        );
    }

    public function testGetActivityDescriptionWithCustomThreshold(): void
    {
        // Arrange
        $checker = new ClaimedStatementsActivityChecker($this->mockStatementRepository, 60);

        // Act
        $description = $checker->getActivityDescription();

        // Assert
        $this->assertEquals(
            'User has claimed statements or segments with activity within the last 60 days',
            $description
        );
    }

    public function testGetPriorityReturnsMediumHighPriority(): void
    {
        // Act
        $priority = $this->sut->getPriority();

        // Assert
        $this->assertEquals(75, $priority);
    }

    public function testGetDayThresholdReturnsCorrectValue(): void
    {
        // Act
        $threshold = $this->sut->getDayThreshold();

        // Assert
        $this->assertEquals(90, $threshold);
    }

    public function testSetDayThresholdUpdatesValue(): void
    {
        // Arrange
        $newThreshold = 120;

        // Act
        $this->sut->setDayThreshold($newThreshold);

        // Assert
        $this->assertEquals($newThreshold, $this->sut->getDayThreshold());
    }

    public function testSetDayThresholdAffectsActivityCheck(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $statement = $this->createMock(Statement::class);
        $modification100DaysAgo = new DateTimeImmutable('-100 days');

        $statement->method('getModified')->willReturn($modification100DaysAgo);

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$statement]);

        // Act - First with 90 day threshold (should be inactive)
        $resultWith90Days = $this->sut->isUserActive($user);

        // Act - Then with 120 day threshold (should be active)
        $this->sut->setDayThreshold(120);
        $resultWith120Days = $this->sut->isUserActive($user);

        // Assert
        $this->assertFalse($resultWith90Days);
        $this->assertTrue($resultWith120Days);
    }

    public function testIsUserActiveWithMixedStatementsReturnsCorrectResult(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);
        $statementWithNullDate = $this->createMock(Statement::class);
        $oldStatement = $this->createMock(Statement::class);
        $recentStatement = $this->createMock(Statement::class);

        $statementWithNullDate->method('getModified')->willReturn(null);
        $oldStatement->method('getModified')->willReturn(new DateTimeImmutable('-200 days'));
        $recentStatement->method('getModified')->willReturn(new DateTimeImmutable('-30 days'));

        $this->mockStatementRepository
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([$statementWithNullDate, $oldStatement, $recentStatement]);

        // Act
        $result = $this->sut->isUserActive($user);

        // Assert
        $this->assertTrue($result);
    }

    public function testRepositoryIsCalledWithCorrectParameters(): void
    {
        // Arrange
        $user = $this->createMock(UserInterface::class);

        $this->mockStatementRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['assignee' => $user])
            ->willReturn([]);

        // Act
        $this->sut->isUserActive($user);

        // Assert - Verified by expects() above
    }
}
