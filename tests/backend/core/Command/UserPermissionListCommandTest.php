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
use demosplan\DemosPlanCoreBundle\Command\UserPermissionListCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class UserPermissionListCommandTest extends FunctionalTestCase
{
    protected $sut;
    private CommandTester $commandTester;
    private User|Proxy|null $testUser;
    private Orga|Proxy|null $testOrga;
    private Customer|Proxy|null $testCustomer;
    private Role|null $testRole;
    private UserAccessControlService $userAccessControlService;

    protected function setUp(): void
    {
        parent::setUp();

        $roleHandler = $this->getContainer()->get(\demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler::class);
        $this->userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $parameterBag = $this->getContainer()->get(ParameterBagInterface::class);

        $this->sut = new UserPermissionListCommand(
            $this->userAccessControlService,
            $userRepository,
            $parameterBag
        );

        $this->commandTester = new CommandTester($this->sut);

        // Set up test data
        $roles = $roleHandler->getUserRolesByCodes([RoleInterface::PUBLIC_AGENCY_WORKER]);
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

    public function testListPermissionsInTableFormatWithNoPermissions(): void
    {
        // Arrange
        $userId = $this->testUser->object()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User-Specific Permissions for "' . $this->testUser->object()->getLogin() . '"', $output);
        self::assertStringContainsString('No user-specific permissions found', $output);
    }

    public function testListPermissionsInTableFormatWithPermissions(): void
    {
        // Arrange
        $userId = $this->testUser->object()->getId();
        $permission1 = 'area_admin_procedures';
        $permission2 = 'feature_statement_bulk_edit';

        // Grant some permissions first
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission1,
            $this->testRole
        );
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
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
        self::assertStringContainsString('User-Specific Permissions for "' . $this->testUser->object()->getLogin() . '"', $output);
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
        $userId = $this->testUser->object()->getId();
        $permission = 'area_admin_procedures';

        // Grant a permission first
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            '--format' => 'json',
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
        self::assertSame($this->testUser->object()->getId(), $jsonData['user']['id']);
        self::assertSame($this->testUser->object()->getLogin(), $jsonData['user']['login']);

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
        $userId = $this->testUser->object()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            '--format' => 'json',
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
        self::assertStringContainsString('User with ID "' . $invalidUserId . '" not found', $output);
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
        $userId = $this->testUser->object()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
            '--format' => 'xml', // Invalid format
        ]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Invalid format. Use "table" or "json".', $output);
    }

    public function testListPermissionsDefaultsToTableFormat(): void
    {
        // Arrange
        $userId = $this->testUser->object()->getId();

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
        $userId = $this->testUser->object()->getId();

        // Act
        $exitCode = $this->commandTester->execute([
            'user-id' => $userId,
        ]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify user information is displayed
        self::assertStringContainsString($this->testUser->object()->getLogin(), $output);
        self::assertStringContainsString($this->testUser->object()->getId(), $output);
        self::assertStringContainsString($this->testOrga->object()->getName(), $output);
        self::assertStringContainsString($this->testCustomer->object()->getName(), $output);
    }
}
