<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHasher;
use PHPUnit\Framework\TestCase;

class UserHasherTokenInvalidationTest extends TestCase
{
    private UserHasher $userHasher;

    protected function setUp(): void
    {
        // Create mock for GlobalConfigInterface
        $mockGlobalConfig = $this->createMock(GlobalConfigInterface::class);
        $mockGlobalConfig->method('getSalt')->willReturn('test-salt');

        $this->userHasher = new UserHasher($mockGlobalConfig);
    }

    public function testPasswordEditHashChangesWhenLastLoginChanges(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));

        // Act - Generate hash with original lastLogin
        $originalHash = $this->userHasher->getPasswordEditHash($user);

        // Update lastLogin
        $user->setLastLogin(new DateTime('2023-01-02 12:00:00'));

        // Generate hash with new lastLogin
        $newHash = $this->userHasher->getPasswordEditHash($user);

        // Assert
        $this->assertNotEquals($originalHash, $newHash, 'Password hash should change when lastLogin timestamp changes');
    }

    public function testChangeEmailHashChangesWhenLastLoginChanges(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));
        $newEmail = 'new@example.com';

        // Act - Generate hash with original lastLogin
        $originalHash = $this->userHasher->getChangeEmailHash($user, $newEmail);

        // Update lastLogin
        $user->setLastLogin(new DateTime('2023-01-02 12:00:00'));

        // Generate hash with new lastLogin
        $newHash = $this->userHasher->getChangeEmailHash($user, $newEmail);

        // Assert
        $this->assertNotEquals($originalHash, $newHash, 'Email change hash should change when lastLogin timestamp changes');
    }

    public function testPasswordTokenValidationFailsAfterLastLoginChange(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));

        // Generate token with original lastLogin
        $token = $this->userHasher->getPasswordEditHash($user);

        // Act - Verify token is valid with original lastLogin
        $isValidBefore = $this->userHasher->isValidPasswordEditHash($user, $token);

        // Update lastLogin (simulating login or token invalidation)
        $user->setLastLogin(new DateTime('2023-01-02 12:00:00'));

        // Check if token is still valid
        $isValidAfter = $this->userHasher->isValidPasswordEditHash($user, $token);

        // Assert
        $this->assertTrue($isValidBefore, 'Token should be valid before lastLogin change');
        $this->assertFalse($isValidAfter, 'Token should be invalid after lastLogin change');
    }

    public function testEmailChangeTokenValidationFailsAfterLastLoginChange(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));
        $newEmail = 'new@example.com';

        // Generate token with original lastLogin
        $token = $this->userHasher->getChangeEmailHash($user, $newEmail);

        // Act - Verify token is valid with original lastLogin
        $isValidBefore = $this->userHasher->isValidChangeEmailHash($user, $newEmail, $token);

        // Update lastLogin (simulating login or token invalidation)
        $user->setLastLogin(new DateTime('2023-01-02 12:00:00'));

        // Check if token is still valid
        $isValidAfter = $this->userHasher->isValidChangeEmailHash($user, $newEmail, $token);

        // Assert
        $this->assertTrue($isValidBefore, 'Token should be valid before lastLogin change');
        $this->assertFalse($isValidAfter, 'Token should be invalid after lastLogin change');
    }
}
