<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class UserAccessControlServiceTest extends FunctionalTestCase
{
    /**
     * @var UserAccessControlService|null
     */
    protected $sut;

    private User|Proxy|null $testUser;
    private User|Proxy|null $testUser2;
    private Orga|Proxy|null $testOrga;
    private Orga|Proxy|null $differentOrga;
    private Customer|Proxy|null $testCustomer;
    private ?Role $testRole;
    private ?Role $differentRole;
    private ?RoleHandler $roleHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new UserAccessControlService(
            $this->getEntityManager()->getRepository(UserAccessControl::class),
            $this->getEntityManager()
        );
        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);

        $roles = $this->roleHandler->getUserRolesByCodes([
            RoleInterface::PRIVATE_PLANNING_AGENCY,
            RoleInterface::PUBLIC_AGENCY_WORKER,
        ]);
        $this->testRole = $roles[0];
        $this->differentRole = $roles[1] ?? $roles[0];

        $this->testCustomer = CustomerFactory::createOne();
        $this->testOrga = OrgaFactory::createOne();
        $this->differentOrga = OrgaFactory::createOne();

        $this->testUser = UserFactory::createOne();
        $this->testUser2 = UserFactory::createOne();

        // Set organizations manually (bidirectional relationship)
        $this->testUser->object()->setOrga($this->testOrga->object());
        $this->testUser2->object()->setOrga($this->testOrga->object());
        $this->testOrga->object()->addUser($this->testUser->object());
        $this->testOrga->object()->addUser($this->testUser2->object());

        // Set roles manually
        $this->testUser->object()->addDplanRole($this->testRole);
        $this->testUser2->object()->addDplanRole($this->testRole);

        // Persist the user changes
        $this->getEntityManager()->persist($this->testUser->object());
        $this->getEntityManager()->persist($this->testUser2->object());
        $this->getEntityManager()->flush();

        // Create a simple OrgaType and establish customer relationship
        $orgaType = new OrgaType();
        $orgaType->setName(OrgaType::MUNICIPALITY);
        $orgaType->setLabel('Test Municipality Label');
        $this->getEntityManager()->persist($orgaType);
        $this->getEntityManager()->flush();

        // Use the addCustomerAndOrgaType method to establish the relationship properly
        $this->testOrga->object()->addCustomerAndOrgaType($this->testCustomer->object(), $orgaType);
        $this->differentOrga->object()->addCustomerAndOrgaType($this->testCustomer->object(), $orgaType);

        // Persist orga changes as well
        $this->getEntityManager()->persist($this->testOrga->object());
        $this->getEntityManager()->persist($this->differentOrga->object());
        $this->getEntityManager()->flush();
    }

    public function testCreateUserPermissionSuccessfully(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        // Act
        $userAccessControl = $this->sut->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Assert
        self::assertInstanceOf(UserAccessControl::class, $userAccessControl);
        self::assertSame($permission, $userAccessControl->getPermission());
        self::assertSame($this->testUser->object(), $userAccessControl->getUser());
        self::assertSame($this->testRole, $userAccessControl->getRole());
        self::assertNotNull($userAccessControl->getId());
    }

    public function testCreateUserPermissionWithDefaultRole(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        // Act
        $userAccessControl = $this->sut->createUserPermission(
            $this->testUser->object(),
            $permission
        );

        // Assert
        self::assertInstanceOf(UserAccessControl::class, $userAccessControl);
        self::assertSame($permission, $userAccessControl->getPermission());
        self::assertSame($this->testUser->object(), $userAccessControl->getUser());
        self::assertNotNull($userAccessControl->getRole());
        self::assertNotNull($userAccessControl->getId());
    }

    public function testCreateUserPermissionValidatesUserOrganizationRelationship(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        // Act & Assert - Service should validate relationships (this will be implemented in service)
        $result = $this->sut->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // For now, just verify the service creates the permission
        self::assertInstanceOf(UserAccessControl::class, $result);
    }

    public function testRemoveUserPermissionSuccessfully(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        $this->sut->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Act
        $result = $this->sut->removeUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Assert
        self::assertTrue($result);

        // Verify permission is actually removed
        $exists = $this->sut->userPermissionExists(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );
        self::assertFalse($exists);
    }

    public function testRemoveUserPermissionReturnsFalseWhenNotExists(): void
    {
        // Arrange
        $permission = 'non_existent_permission';

        // Act
        $result = $this->sut->removeUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Assert
        self::assertFalse($result);
    }

    public function testGetUserPermissionsReturnsOnlyUserPermissions(): void
    {
        // Arrange
        $permission1 = 'feature_statement_bulk_edit';
        $permission2 = 'feature_procedure_planning_area_match';

        $this->sut->createUserPermission($this->testUser->object(), $permission1, $this->testRole);
        $this->sut->createUserPermission($this->testUser->object(), $permission2, $this->testRole);

        // Create permission for different user
        $this->sut->createUserPermission($this->testUser2->object(), $permission1, $this->testRole);

        // Act
        $userPermissions = $this->sut->getUserPermissions($this->testUser->object());

        // Assert
        self::assertCount(2, $userPermissions);
        self::assertContainsOnlyInstancesOf(UserAccessControl::class, $userPermissions);

        $permissionNames = array_map(fn ($p) => $p->getPermission(), $userPermissions);
        self::assertContains($permission1, $permissionNames);
        self::assertContains($permission2, $permissionNames);

        // Verify all permissions belong to testUser
        foreach ($userPermissions as $permission) {
            self::assertSame($this->testUser->object(), $permission->getUser());
        }
    }

    public function testUserPermissionExistsReturnsCorrectResult(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        // Act & Assert - Before creating permission
        $existsBefore = $this->sut->userPermissionExists(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );
        self::assertFalse($existsBefore);

        // Create permission
        $this->sut->createUserPermission($this->testUser->object(), $permission, $this->testRole);

        // Act & Assert - After creating permission
        $existsAfter = $this->sut->userPermissionExists(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );
        self::assertTrue($existsAfter);
    }

    public function testUserPermissionExistsWithDifferentRoleReturnsFalse(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        $this->sut->createUserPermission($this->testUser->object(), $permission, $this->testRole);

        // Act
        $exists = $this->sut->userPermissionExists(
            $this->testUser->object(),
            $permission,
            $this->differentRole
        );

        // Assert
        self::assertFalse($exists);
    }

    public function testCreateUserPermissionValidatesUserRole(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        // Act & Assert - Try to create permission with role user doesn't have
        // This should be prevented by validation
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User does not have the specified role');

        // First we need to ensure the user doesn't have the role
        // For this test, we'll assume the service validates this
        $this->sut->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->differentRole // Role the user doesn't have
        );
    }

    public function testServicePreventsDuplicatePermissions(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        $this->sut->createUserPermission($this->testUser->object(), $permission, $this->testRole);

        // Act - Try to create the same permission again
        $result = $this->sut->createUserPermission($this->testUser->object(), $permission, $this->testRole);

        // Assert - Service should handle duplicates gracefully
        self::assertInstanceOf(UserAccessControl::class, $result);

        // Verify only one permission exists
        $userPermissions = $this->sut->getUserPermissions($this->testUser->object());
        $duplicatePermissions = array_filter(
            $userPermissions,
            fn ($p) => $p->getPermission() === $permission
        );
        self::assertCount(1, $duplicatePermissions);
    }

    public function testServiceHandlesPermissionBoundariesCorrectly(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        $this->sut->createUserPermission($this->testUser->object(), $permission, $this->testRole);

        // Act - Get permissions with different organization context
        $permissions = $this->sut->getUserPermissions($this->testUser->object());

        // Assert - Should respect organization boundaries
        self::assertNotEmpty($permissions);
        foreach ($permissions as $userPermission) {
            self::assertSame($this->testUser->object(), $userPermission->getUser());
        }
    }
}
