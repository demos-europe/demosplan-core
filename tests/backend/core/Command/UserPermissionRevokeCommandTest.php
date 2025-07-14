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
use demosplan\DemosPlanCoreBundle\Command\UserPermissionRevokeCommand;
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

class UserPermissionRevokeCommandTest extends FunctionalTestCase
{
    protected $sut;
    private CommandTester $commandTester;
    private User|Proxy|null $testUser;
    private Orga|Proxy|null $testOrga;
    private Customer|Proxy|null $testCustomer;
    private Role|null $testRole;
    private RoleHandler|null $roleHandler;
    private UserAccessControlService $userAccessControlService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $parameterBag = $this->getContainer()->get(ParameterBagInterface::class);

        $this->sut = new UserPermissionRevokeCommand(
            $this->userAccessControlService,
            $userRepository,
            $this->roleHandler,
            $parameterBag
        );

        $this->commandTester = new CommandTester($this->sut);

        // Set up test data
        $roles = $this->roleHandler->getUserRolesByCodes([RoleInterface::PUBLIC_AGENCY_WORKER]);
        $this->testRole = $roles[0];

        $this->testCustomer = CustomerFactory::createOne();
        $this->testOrga = OrgaFactory::createOne();
        $this->testUser = UserFactory::createOne();

        // Set up bidirectional relationships
        $this->testUser->object()->setOrga($this->testOrga->object());
        $this->testOrga->object()->addUser($this->testUser->object());
        $this->testUser->object()->addDplanRole($this->testRole);

        // Create OrgaType and establish customer relationship
        $orgaType = new OrgaType();
        $orgaType->setName(OrgaType::MUNICIPALITY);
        $orgaType->setLabel('Test Municipality Label');
        $this->getEntityManager()->persist($orgaType);
        $this->getEntityManager()->flush();

        $this->testOrga->object()->addCustomerAndOrgaType($this->testCustomer->object(), $orgaType);
        $this->testUser->object()->setCurrentCustomer($this->testCustomer->object());

        // Persist changes
        $this->getEntityManager()->persist($this->testUser->object());
        $this->getEntityManager()->persist($this->testOrga->object());
        $this->getEntityManager()->flush();
    }

    public function testRevokePermissionSuccessfully(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->object()->getId();

        // First grant the permission
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Verify permission exists
        $initialPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser->object(), 'permission' => $permission]);
        self::assertCount(1, $initialPermissions);

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission revoked successfully!', $output);
        self::assertStringContainsString($this->testUser->object()->getLogin(), $output);
        self::assertStringContainsString($permission, $output);

        // Verify permission was actually removed from database
        $remainingPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser->object(), 'permission' => $permission]);
        self::assertCount(0, $remainingPermissions);
    }

    public function testRevokePermissionWithSpecificRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->object()->getId();
        $roleCode = $this->testRole->getCode();

        // First grant the permission
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
            '--role' => $roleCode,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission revoked successfully!', $output);
        self::assertStringContainsString($roleCode, $output);

        // Verify permission was removed for the correct role
        $remainingPermissions = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->findBy(['user' => $this->testUser->object(), 'permission' => $permission, 'role' => $this->testRole]);
        self::assertCount(0, $remainingPermissions);
    }

    public function testRevokePermissionFailsWithInvalidUserId(): void
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

    public function testRevokePermissionFailsWithEmptyUserId(): void
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

    public function testRevokePermissionFailsWithInvalidRoleCode(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->object()->getId();
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

    public function testRevokePermissionWarnsWhenPermissionDoesNotExist(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->object()->getId();

        // Act (try to revoke permission that doesn't exist)
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            'permission' => $permission,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('does not have permission', $output);
    }

    public function testRevokePermissionFailsWithInvalidPermissionName(): void
    {
        // Arrange
        $invalidPermission = ''; // Empty permission
        $userId = $this->testUser->object()->getId();

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

    public function testRevokePermissionFailsWithBadlyFormattedPermissionName(): void
    {
        // Arrange
        $invalidPermission = '123_invalid_permission'; // Starts with number
        $userId = $this->testUser->object()->getId();

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

    public function testRevokePermissionFailsWhenUserDoesNotHaveSpecifiedRole(): void
    {
        // Arrange
        $permission = 'area_admin_procedures';
        $userId = $this->testUser->object()->getId();

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
}
