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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\UserPermissionGrantCommand;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class UserPermissionGrantCommandTest extends FunctionalTestCase
{
    private const ROLE_OPTION = '--role';

    protected $sut;
    private ?CommandTester $commandTester = null;
    private ?User $testUser = null;
    private ?Role $testRole = null;
    private ?RoleHandler $roleHandler = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Use fixture user instead of creating new ones to avoid cascade persistence issues
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);

        $parameterBag = $this->getContainer()->get(ParameterBagInterface::class);

        $this->sut = new UserPermissionGrantCommand(
            $userAccessControlService,
            $userRepository,
            $this->roleHandler,
            $parameterBag
        );

        $this->commandTester = $this->createCommandTester();

        // Use the test user's actual role instead of assuming a specific role
        $this->testRole = $this->testUser->getDplanRoles()->first();
    }

    private function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);
        $application->add($this->sut);

        return new CommandTester($this->sut);
    }

    public function testGrantPermissionSuccessfullyWithDefaultRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'    => $userId,
            'permission' => $permission,
        ]);

        // Assert
        $output = $this->commandTester->getDisplay();
        if (Command::SUCCESS !== $exitCode) {
            $this->fail("Command failed with exit code $exitCode. Output: ".$output);
        }
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Permission granted successfully!', $output);
        self::assertStringContainsString($this->testUser->getLogin(), $output);
        self::assertStringContainsString($permission, $output);
        self::assertStringContainsString($this->testRole->getCode(), $output);

        // Verify permission was actually created in database
        $userPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser, 'permission' => $permission]);
        self::assertCount(1, $userPermissions);
        self::assertSame($permission, $userPermissions[0]->getPermission());
    }

    public function testGrantPermissionSuccessfullyWithSpecificRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->getId();
        $roleCode = $this->testRole->getCode();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'         => $userId,
            'permission'      => $permission,
            self::ROLE_OPTION => $roleCode,
        ]);

        // Assert
        $output = $this->commandTester->getDisplay();
        if (Command::SUCCESS !== $exitCode) {
            $this->fail("Command failed with exit code $exitCode. Output: ".$output);
        }
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Permission granted successfully!', $output);
        self::assertStringContainsString($roleCode, $output);

        // Verify permission was created with correct role
        $userPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser, 'permission' => $permission, 'role' => $this->testRole]);
        self::assertCount(1, $userPermissions);
    }

    public function testGrantPermissionFailsWithInvalidUserId(): void
    {
        // Arrange
        $invalidUserId = '00000000-0000-0000-0000-000000000000';
        $permission = 'area_admin_procedures';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'    => $invalidUserId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User with ID "'.$invalidUserId.'" not found', $output);
    }

    public function testGrantPermissionFailsWithEmptyUserId(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'    => '',
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID cannot be empty', $output);
    }

    public function testGrantPermissionFailsWithInvalidRoleCode(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->getId();
        $invalidRoleCode = 'INVALID_ROLE';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'         => $userId,
            'permission'      => $permission,
            self::ROLE_OPTION => $invalidRoleCode,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role with code "'.$invalidRoleCode.'" not found', $output);
    }

    public function testGrantPermissionFailsWhenUserDoesNotHaveSpecifiedRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->getId();

        // Get a different role that the user doesn't have (use PUBLIC_AGENCY_WORKER since FP_ONLY user has PLANNING_AGENCY roles)
        $differentRoles = $this->roleHandler->getUserRolesByCodes([RoleInterface::PUBLIC_AGENCY_WORKER]);
        $differentRoleCode = $differentRoles[0]->getCode();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'         => $userId,
            'permission'      => $permission,
            self::ROLE_OPTION => $differentRoleCode,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('does not have role "'.$differentRoleCode.'"', $output);
    }

    public function testGrantPermissionFailsWithInvalidPermissionName(): void
    {
        // Arrange
        $invalidPermission = ''; // Empty permission
        $userId = $this->testUser->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'    => $userId,
            'permission' => $invalidPermission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name cannot be empty', $output);
    }

    public function testGrantPermissionFailsWithBadlyFormattedPermissionName(): void
    {
        // Arrange
        $invalidPermission = '123_invalid_permission'; // Starts with number
        $userId = $this->testUser->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'    => $userId,
            'permission' => $invalidPermission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name must start with a letter', $output);
    }

    public function testGrantPermissionSucceedsWhenPermissionAlreadyExists(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->getId();

        // Create permission first time
        $this->commandTester->execute([
            'user-id'    => $userId,
            'permission' => $permission,
        ]);

        // Act - Try to grant same permission again
        $exitCode = $this->commandTester->execute([
            'user-id'    => $userId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('already has permission', $output);

        // Verify only one permission exists in database
        $userPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser, 'permission' => $permission]);
        self::assertCount(1, $userPermissions, 'Should not create duplicate permissions');
    }

    public function testGrantPermissionFailsWhenUserHasNoOrganization(): void
    {
        // Arrange - Use guest user that typically has no organization
        $guestUser = $this->fixtures->getReference(LoadUserData::TEST_USER_GUEST);
        $permission = 'area_admin_procedures';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id'    => $guestUser->getId(),
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('does not have an organization assigned', $output);
    }
}
