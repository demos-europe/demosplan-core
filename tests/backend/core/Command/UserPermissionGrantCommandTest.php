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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class UserPermissionGrantCommandTest extends FunctionalTestCase
{
    protected $sut;
    private CommandTester $commandTester;
    private User|Proxy|null $testUser;
    private Orga|Proxy|null $testOrga;
    private Customer|Proxy|null $testCustomer;
    private Role|null $testRole;
    private RoleHandler|null $roleHandler;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Set up test data - use a simpler approach
        $roles = $this->roleHandler->getUserRolesByCodes([RoleInterface::PUBLIC_AGENCY_WORKER]);
        $this->testRole = $roles[0];

        // Create entities step by step to avoid cascading issues
        $this->testCustomer = CustomerFactory::createOne();
        $this->testOrga = OrgaFactory::createOne();
        $this->testUser = UserFactory::createOne();
        
        // Clear entity manager and refresh entities
        $this->getEntityManager()->clear();
        $this->testCustomer = $this->testCustomer->_refresh();
        $this->testOrga = $this->testOrga->_refresh();
        $this->testUser = $this->testUser->_refresh();
        
        // Set up minimal relationships
        $this->testUser->_real()->setOrga($this->testOrga->_real());
        $this->testUser->_real()->setCurrentCustomer($this->testCustomer->_real());
        
        // Persist without role first
        $this->getEntityManager()->persist($this->testUser->_real());
        $this->getEntityManager()->flush();
        
        // Then add role
        $this->testUser->_real()->addDplanRole($this->testRole);
        $this->getEntityManager()->persist($this->testUser->_real());
        $this->getEntityManager()->flush();
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
        $userId = $this->testUser->_real()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission granted successfully!', $output);
        self::assertStringContainsString($this->testUser->_real()->getLogin(), $output);
        self::assertStringContainsString($permission, $output);
        self::assertStringContainsString($this->testRole->getCode(), $output);

        // Verify permission was actually created in database
        $userPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser->_real(), 'permission' => $permission]);
        self::assertCount(1, $userPermissions);
        self::assertSame($permission, $userPermissions[0]->getPermission());
    }

    public function testGrantPermissionSuccessfullyWithSpecificRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->_real()->getId();
        $roleCode = $this->testRole->getCode();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
            '--role' => $roleCode,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission granted successfully!', $output);
        self::assertStringContainsString($roleCode, $output);

        // Verify permission was created with correct role
        $userPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser->_real(), 'permission' => $permission, 'role' => $this->testRole]);
        self::assertCount(1, $userPermissions);
    }

    public function testGrantPermissionFailsWithInvalidUserId(): void
    {
        // Arrange
        $invalidUserId = '00000000-0000-0000-0000-000000000000';
        $permission = 'area_admin_procedures';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $invalidUserId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User with ID "' . $invalidUserId . '" not found', $output);
    }

    public function testGrantPermissionFailsWithEmptyUserId(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => '',
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
        $userId = $this->testUser->_real()->getId();
        $invalidRoleCode = 'INVALID_ROLE';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
            '--role' => $invalidRoleCode,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role with code "' . $invalidRoleCode . '" not found', $output);
    }

    public function testGrantPermissionFailsWhenUserDoesNotHaveSpecifiedRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->_real()->getId();

        // Get a different role that the user doesn't have
        $differentRoles = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY]);
        $differentRoleCode = $differentRoles[0]->getCode();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
            '--role' => $differentRoleCode,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('does not have role "' . $differentRoleCode . '"', $output);
    }

    public function testGrantPermissionFailsWithInvalidPermissionName(): void
    {
        // Arrange
        $invalidPermission = ''; // Empty permission
        $userId = $this->testUser->_real()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
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
        $userId = $this->testUser->_real()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
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
        $userId = $this->testUser->_real()->getId();

        // Create permission first time
        $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
        ]);

        // Act - Try to grant same permission again
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('already has permission', $output);

        // Verify only one permission exists in database
        $userPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser->_real(), 'permission' => $permission]);
        self::assertCount(1, $userPermissions, 'Should not create duplicate permissions');
    }

    public function testGrantPermissionFailsWhenUserHasNoOrganization(): void
    {
        // Arrange
        $userWithoutOrga = UserFactory::createOne();
        $userWithoutOrga->_real()->addDplanRole($this->testRole);
        $this->getEntityManager()->persist($userWithoutOrga->_real());
        $this->getEntityManager()->flush();

        $permission = 'area_admin_procedures';

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userWithoutOrga->_real()->getId(),
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('does not have an organization assigned', $output);
    }
}
