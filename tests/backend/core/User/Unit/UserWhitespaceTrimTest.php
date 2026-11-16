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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHasher;
use PHPUnit\Framework\TestCase;

class UserWhitespaceTrimTest extends TestCase
{
    private const ADDRESS = 'max.mustermann@example.com';

    public function testSetEmailTrimsSurroundingWhitespace(): void
    {
        // Arrange
        $user = new User();

        // Act
        $user->setEmail(' '.self::ADDRESS.' ');

        // Assert
        self::assertSame(self::ADDRESS, $user->getEmail());
    }

    public function testSetLoginTrimsSurroundingWhitespace(): void
    {
        // Arrange
        $user = new User();

        // Act
        $user->setLogin(self::ADDRESS.' ');

        // Assert
        self::assertSame(self::ADDRESS, $user->getLogin());
    }

    public function testSetLoginKeepsNull(): void
    {
        // Arrange
        $user = new User();

        // Act
        $user->setLogin(null);

        // Assert
        self::assertNull($user->getLogin());
    }

    public function testPasswordEditHashIsIdenticalForPaddedAndTrimmedInput(): void
    {
        // Arrange: a fixed-length CHAR column drops trailing spaces on save, so the token built from the
        // freshly created entity must equal the one built from the reloaded (trimmed) entity.
        $globalConfig = $this->createMock(GlobalConfigInterface::class);
        $globalConfig->method('getSalt')->willReturn('test-salt');
        $hasher = new UserHasher($globalConfig);

        $created = new User();
        $created->setLogin(self::ADDRESS.' ');
        $reloaded = new User();
        $reloaded->setLogin(self::ADDRESS);

        // Act
        $tokenInMail = $hasher->getPasswordEditHash($created);

        // Assert
        self::assertTrue($hasher->isValidPasswordEditHash($reloaded, $tokenInMail));
    }
}
