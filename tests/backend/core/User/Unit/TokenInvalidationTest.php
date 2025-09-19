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

/**
 * Test to verify token invalidation after login.
 *
 * This test checks that tokens become invalid after lastLogin changes,
 * simulating what happens when a user completes the password reset or email change process.
 */
class TokenInvalidationTest extends TestCase
{
    private UserHasher $userHasher;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for GlobalConfigInterface
        $mockGlobalConfig = $this->createMock(GlobalConfigInterface::class);
        $mockGlobalConfig->method('getSalt')->willReturn('test-salt');

        // Create service with mocked dependencies
        $this->userHasher = new UserHasher($mockGlobalConfig);
    }

    /**
     * Test that controller's email change action properly invalidates tokens.
     */
    public function testEmailChangeConfirmationInvalidatesToken(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setEmail('old@example.com');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));
        $newEmail = 'new@example.com';

        // Store original hash
        $originalHash = $this->userHasher->getChangeEmailHash($user, $newEmail);

        // Simulate what the controller does: update lastLogin
        $user->setLastLogin(new DateTime());

        // Act - check if original hash is still valid
        $isHashStillValid = $this->userHasher->isValidChangeEmailHash($user, $newEmail, $originalHash);

        // Assert
        $this->assertFalse($isHashStillValid, 'Token should be invalidated after lastLogin is updated');
    }

    /**
     * Test that controller's password set action properly invalidates tokens.
     */
    public function testPasswordSetActionInvalidatesToken(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));

        // Store original hash
        $originalToken = $this->userHasher->getPasswordEditHash($user);

        // Simulate what the controller does: update lastLogin
        $user->setLastLogin(new DateTime());

        // Act - check if original token is still valid
        $isTokenStillValid = $this->userHasher->isValidPasswordEditHash($user, $originalToken);

        // Assert
        $this->assertFalse($isTokenStillValid, 'Password token should be invalidated after lastLogin is updated');
    }

    /**
     * Test that hash validity consistently depends on lastLogin for both email and password tokens.
     */
    public function testConsistentTokenInvalidation(): void
    {
        // Arrange
        $user = new User();
        $user->setLogin('testuser');
        $user->setLastLogin(new DateTime('2023-01-01 12:00:00'));
        $newEmail = 'new@example.com';

        // Generate tokens
        $passwordToken = $this->userHasher->getPasswordEditHash($user);
        $emailToken = $this->userHasher->getChangeEmailHash($user, $newEmail);

        // Verify tokens are valid
        $this->assertTrue($this->userHasher->isValidPasswordEditHash($user, $passwordToken), 'Original password token should be valid');
        $this->assertTrue($this->userHasher->isValidChangeEmailHash($user, $newEmail, $emailToken), 'Original email token should be valid');

        // Simulate user action that updates lastLogin
        $user->setLastLogin(new DateTime());

        // Assert - check both tokens are now invalid
        $this->assertFalse($this->userHasher->isValidPasswordEditHash($user, $passwordToken), 'Password token should be invalidated');
        $this->assertFalse($this->userHasher->isValidChangeEmailHash($user, $newEmail, $emailToken), 'Email token should be invalidated');
    }
}
