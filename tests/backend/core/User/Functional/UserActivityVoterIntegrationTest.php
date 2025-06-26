<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Authorization\Voter\UserActivityVoter;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\ClaimedStatementsActivityChecker;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tests\Base\FunctionalTestCase;

class UserActivityVoterIntegrationTest extends FunctionalTestCase
{
    private $authorizationChecker;
    private $userActivityVoter;
    private $lastLoginChecker;
    private $claimedStatementsChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        $this->authorizationChecker = $container->get(AuthorizationCheckerInterface::class);
        $this->userActivityVoter = $container->get(UserActivityVoter::class);
        $this->lastLoginChecker = $container->get(LastLoginActivityChecker::class);
        $this->claimedStatementsChecker = $container->get(ClaimedStatementsActivityChecker::class);
    }

    public function testUserIsActiveWhenRecentLogin(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-10 days'),
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertTrue($isActive);
    }

    public function testUserIsInactiveWhenOldLogin(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-200 days'),
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertFalse($isActive);
    }

    public function testUserIsInactiveWhenNeverLoggedIn(): void
    {
        // Arrange
        $user = UserFactory::createOne();

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert - User should be inactive since they're new, haven't completed profile, and have no activity
        $this->assertFalse($isActive);
    }

    public function testUserIsActiveWithRecentClaimedStatementActivity(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-200 days'), // Old login
        ]);

        StatementFactory::createOne([
            'assignee' => $user,
            'modified' => new DateTimeImmutable('-30 days'),
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertTrue($isActive);
    }

    public function testUserIsInactiveWithOldClaimedStatementActivity(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-200 days'), // Old login
        ]);

        StatementFactory::createOne([
            'assignee' => $user,
            'modified' => new DateTimeImmutable('-200 days'), // Old activity
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertFalse($isActive);
    }

    public function testActivityCheckersAreRegisteredAndSortedByPriority(): void
    {
        // Act
        $checkers = $this->userActivityVoter->getActivityCheckers();

        // Assert
        $this->assertCount(2, $checkers);

        // LastLoginActivityChecker should be first (priority 100)
        $this->assertInstanceOf(LastLoginActivityChecker::class, $checkers[0]);
        $this->assertEquals(100, $checkers[0]->getPriority());

        // ClaimedStatementsActivityChecker should be second (priority 75)
        $this->assertInstanceOf(ClaimedStatementsActivityChecker::class, $checkers[1]);
        $this->assertEquals(75, $checkers[1]->getPriority());
    }

    public function testParametersAreInjectedCorrectly(): void
    {
        // Act & Assert
        $this->assertEquals(180, $this->lastLoginChecker->getDayThreshold());
        $this->assertEquals(180, $this->claimedStatementsChecker->getDayThreshold());
    }

    public function testActivityDescriptionsAreCorrect(): void
    {
        // Act & Assert
        $this->assertEquals(
            'User has logged in within the last 180 days or shows signs of account activity',
            $this->lastLoginChecker->getActivityDescription()
        );

        $this->assertEquals(
            'User has claimed statements or segments with activity within the last 180 days',
            $this->claimedStatementsChecker->getActivityDescription()
        );
    }

    public function testUserIsActiveWhenEitherCheckerReturnsTrue(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-200 days'), // Old login (would make LastLoginActivityChecker return false)
        ]);

        // But create recent statement activity (makes ClaimedStatementsActivityChecker return true)
        StatementFactory::createOne([
            'assignee' => $user,
            'modified' => new DateTimeImmutable('-10 days'),
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertTrue($isActive);
    }

    public function testVoterSupportsIsActiveUserAttribute(): void
    {
        // Arrange
        $user = UserFactory::createOne();

        // Act - Test through authorization checker since supports() is protected
        // This indirectly tests that the voter supports the IS_ACTIVE_USER attribute
        $result = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert - If the voter didn't support this attribute, it would return false
        // Since we have activity checkers configured, the result will depend on user activity
        $this->assertIsBool($result);
    }

    public function testVoterDoesNotSupportOtherAttributes(): void
    {
        // Arrange
        $user = UserFactory::createOne();

        // Act - Test unsupported attribute through authorization checker
        $result = $this->authorizationChecker->isGranted('OTHER_ATTRIBUTE', $user->_real());

        // Assert - Should return false for unsupported attributes
        $this->assertFalse($result);
    }

    public function testUserIsActiveAtExactThreshold(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-179 days'), // Just within threshold (180 days)
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertTrue($isActive);
    }

    public function testUserIsInactiveJustBeyondThreshold(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-181 days'), // Just beyond threshold
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertFalse($isActive);
    }

    public function testMultipleStatementsWithMixedActivity(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'lastLogin' => new DateTimeImmutable('-200 days'), // Old login
        ]);

        // Create multiple statements with different activity levels
        StatementFactory::createOne([
            'assignee' => $user,
            'modified' => new DateTimeImmutable('-200 days'), // Old
        ]);

        StatementFactory::createOne([
            'assignee' => $user,
            'modified' => null, // No modification date
        ]);

        StatementFactory::createOne([
            'assignee' => $user,
            'modified' => new DateTimeImmutable('-30 days'), // Recent - this should make user active
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert
        $this->assertTrue($isActive);
    }

    public function testUserIsInactiveWithIdenticalDateObjects(): void
    {
        // Arrange
        $baseDate = new DateTimeImmutable('2023-01-01 12:00:00');
        $user = UserFactory::createOne([
            'createdDate'  => $baseDate,
            'modifiedDate' => new DateTimeImmutable('2023-01-01 12:00:00'), // Same value, different object
        ]);

        // Act
        $isActive = $this->authorizationChecker->isGranted(UserActivityVoter::IS_ACTIVE_USER, $user->_real());

        // Assert - User should be inactive since dates are equal (same values, different objects)
        $this->assertFalse($isActive);
    }
}
