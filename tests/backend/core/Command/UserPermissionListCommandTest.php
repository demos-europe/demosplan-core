<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\UserPermissionListCommand;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class UserPermissionListCommandTest extends FunctionalTestCase
{
    private const FORMAT_OPTION = '--format';

    protected $sut;
    private ?CommandTester $commandTester = null;
    private ?User $testUser = null;
    private ?Role $testRole = null;
    private ?UserAccessControlService $userAccessControlService = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Use fixture user instead of creating new ones to avoid cascade persistence issues
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);

        $this->userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $parameterBag = $this->getContainer()->get(ParameterBagInterface::class);

        $this->sut = new UserPermissionListCommand(
            $this->userAccessControlService,
            $userRepository,
            $parameterBag
        );

        $this->commandTester = $this->createCommandTester();

        // Use the test user's actual role
        $this->testRole = $this->testUser->getDplanRoles()->first();
    }

    private function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);
        $application->add($this->sut);

        return new CommandTester($this->sut);
    }

    public function testListPermissionsInTableFormatWithNoPermissions(): void
    {
        // Arrange
        $userId = $this->testUser->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User-Specific Permissions for "'.$this->testUser->getLogin().'"', $output);
        self::assertStringContainsString('No user-specific permissions found', $output);
    }

    public function testListPermissionsInTableFormatWithPermissions(): void
    {
        // Arrange
        $userId = $this->testUser->getId();
        $permission1 = 'area_admin_procedures';
        $permission2 = 'feature_statement_bulk_edit';

        // Grant some permissions first
        $this->userAccessControlService->createUserPermission(
            $this->testUser,
            $permission1,
            $this->testRole
        );
        $this->userAccessControlService->createUserPermission(
            $this->testUser,
            $permission2,
            $this->testRole
        );

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User-Specific Permissions for "'.$this->testUser->getLogin().'"', $output);
        self::assertStringContainsString('Total Permissions', $output);
        self::assertStringContainsString('2', $output); // Should show 2 permissions
        self::assertStringContainsString($permission1, $output);
        self::assertStringContainsString($permission2, $output);
        self::assertStringContainsString($this->testRole->getCode(), $output);

        // Should contain table headers
        self::assertStringContainsString('Permission', $output);
        self::assertStringContainsString('Role', $output);
        self::assertStringContainsString('Granted Date', $output);
        self::assertStringContainsString('Modified Date', $output);
    }

    public function testListPermissionsInJsonFormat(): void
    {
        // Arrange
        $userId = $this->testUser->getId();
        $permission = 'area_admin_procedures';

        // Grant a permission first
        $this->userAccessControlService->createUserPermission(
            $this->testUser,
            $permission,
            $this->testRole
        );

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'           => $userId,
            self::FORMAT_OPTION => 'json',
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify it's valid JSON
        $jsonData = json_decode($output, true);
        self::assertNotNull($jsonData, 'Output should be valid JSON');

        // Verify JSON structure
        self::assertArrayHasKey('user', $jsonData);
        self::assertArrayHasKey('permissions', $jsonData);

        // Verify user data
        self::assertSame($this->testUser->getId(), $jsonData['user']['id']);
        self::assertSame($this->testUser->getLogin(), $jsonData['user']['login']);

        // Verify permissions data
        self::assertCount(1, $jsonData['permissions']);
        self::assertSame($permission, $jsonData['permissions'][0]['permission']);
        self::assertSame($this->testRole->getCode(), $jsonData['permissions'][0]['role']);
        self::assertArrayHasKey('granted_date', $jsonData['permissions'][0]);
        self::assertArrayHasKey('modified_date', $jsonData['permissions'][0]);
    }

    public function testListPermissionsInJsonFormatWithNoPermissions(): void
    {
        // Arrange
        $userId = $this->testUser->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'           => $userId,
            self::FORMAT_OPTION => 'json',
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify it's valid JSON
        $jsonData = json_decode($output, true);
        self::assertNotNull($jsonData, 'Output should be valid JSON');

        // Verify JSON structure with empty permissions
        self::assertArrayHasKey('user', $jsonData);
        self::assertArrayHasKey('permissions', $jsonData);
        self::assertEmpty($jsonData['permissions']);
    }

    public function testListPermissionsFailsWithInvalidUserId(): void
    {
        // Arrange
        $invalidUserId = '00000000-0000-0000-0000-000000000000';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $invalidUserId,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User with ID "'.$invalidUserId.'" not found', $output);
    }

    public function testListPermissionsFailsWithEmptyUserId(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => '',
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID cannot be empty', $output);
    }

    public function testListPermissionsFailsWithInvalidFormat(): void
    {
        // Arrange
        $userId = $this->testUser->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'           => $userId,
            self::FORMAT_OPTION => 'xml', // Invalid format
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Invalid format. Use "table" or "json".', $output);
    }

    public function testListPermissionsDefaultsToTableFormat(): void
    {
        // Arrange
        $userId = $this->testUser->getId();

        // Act (don't specify format, should default to table)
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should contain table format indicators, not JSON
        self::assertStringContainsString('User-Specific Permissions for', $output);
        self::assertStringNotContainsString('{', $output); // No JSON braces
        self::assertStringNotContainsString('"user":', $output); // No JSON keys
    }

    public function testListPermissionsDisplaysUserInfoCorrectly(): void
    {
        // Arrange
        $userId = $this->testUser->getId();
        $permission = 'area_admin_procedures';

        // Grant a permission so user info section is displayed
        $this->userAccessControlService->createUserPermission(
            $this->testUser,
            $permission,
            $this->testRole
        );

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify user information is displayed (only shown when permissions exist)
        self::assertStringContainsString($this->testUser->getLogin(), $output);
        self::assertStringContainsString($this->testUser->getId(), $output);
        self::assertStringContainsString($this->testUser->getOrga()->getName(), $output);
        self::assertStringContainsString($this->testUser->getCurrentCustomer()->getName(), $output);
    }
}
