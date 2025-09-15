<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Permissions;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class PermissionEvaluationIntegrationTest extends FunctionalTestCase
{
    private User|Proxy|null $testUser;
    private Orga|Proxy|null $testOrga;
    private Customer|Proxy|null $testCustomer;
    private ?Role $testRole;
    private ?RoleHandler $roleHandler;
    private ?UserAccessControlService $userAccessControlService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);
        // permissions will be accessed through currentUserService after login

        // Use PUBLIC_AGENCY_WORKER role which has limited permissions by default
        $roles = $this->roleHandler->getUserRolesByCodes([RoleInterface::PUBLIC_AGENCY_WORKER]);
        $this->testRole = $roles[0];

        $this->testCustomer = CustomerFactory::createOne();
        $this->testOrga = OrgaFactory::createOne();

        $this->testUser = UserFactory::createOne();

        // Set up bidirectional relationship
        $this->testUser->object()->setOrga($this->testOrga->object());
        $this->testOrga->object()->addUser($this->testUser->object());

        // Set roles manually
        $this->testUser->object()->addDplanRole($this->testRole);

        // Persist the user changes
        $this->getEntityManager()->persist($this->testUser->object());
        $this->getEntityManager()->flush();

        // Create OrgaType and establish customer relationship
        $orgaType = new OrgaType();
        $orgaType->setName(OrgaType::MUNICIPALITY);
        $orgaType->setLabel('Test Municipality Label');
        $this->getEntityManager()->persist($orgaType);
        $this->getEntityManager()->flush();

        // Use the addCustomerAndOrgaType method to establish the relationship properly
        $this->testOrga->object()->addCustomerAndOrgaType($this->testCustomer->object(), $orgaType);

        // Set the current customer for the user
        $this->testUser->object()->setCurrentCustomer($this->testCustomer->object());

        // Persist orga and user changes
        $this->getEntityManager()->persist($this->testUser->object());
        $this->getEntityManager()->persist($this->testOrga->object());
        $this->getEntityManager()->flush();
    }

    /**
     * Helper method to ensure permissions are initialized in test environment.
     */
    private function ensurePermissionsInitialized(User $user): void
    {
        $permissions = $this->currentUserService->getPermissions();
        // Always reinitialize permissions to pick up any changes
        $permissions->initPermissions($user);
    }

    public function testUserWithoutSpecificPermissionCannotAccessRestrictedFeature(): void
    {
        // Arrange
        $permission = 'area_manage_orgadata'; // This should be restricted for PUBLIC_AGENCY_WORKER

        // Log in the user to enable permission checking
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());

        // Act & Assert - User should not have this permission by default
        $result = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertFalse($result, 'User should not have permission by default');
    }

    public function testUserWithSpecificPermissionCanAccessRestrictedFeature(): void
    {
        // Arrange
        $permission = 'area_manage_orgadata'; // Admin-only permission that actually exists in the system

        // Grant user-specific permission
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Debug - verify the user-specific permission was created
        $userPermissions = $this->userAccessControlService->getUserPermissions($this->testUser->object());
        self::assertCount(1, $userPermissions, 'Should have 1 user-specific permission');
        self::assertSame($permission, $userPermissions[0]->getPermission(), 'Permission should match');

        // Log in the user to enable permission checking
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());

        // Act & Assert - User should now have this admin permission through user-specific permission
        $result = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertTrue($result, 'User should have permission after granting user-specific access');
    }

    public function testUserSpecificPermissionOverridesRoleBasedDenial(): void
    {
        // Arrange
        $permission = 'area_manage_orgadata'; // Admin-only permission

        // First verify the user doesn't have this permission normally
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());
        $initialResult = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertFalse($initialResult, 'User should not have admin permission initially');

        // Grant user-specific permission
        $userPermission = $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Debug: Verify the permission was created correctly
        self::assertNotNull($userPermission, 'User permission should be created');
        self::assertSame($permission, $userPermission->getPermission());
        self::assertSame($this->testUser->object(), $userPermission->getUser());

        // Debug: Check if service can find the permission
        $foundPermissions = $this->userAccessControlService->getUserPermissions($this->testUser->object());
        self::assertCount(1, $foundPermissions, 'Should find exactly one user permission');

        // Debug: Check user relationships
        self::assertNotNull($this->testUser->object()->getOrga(), 'User should have organization');
        self::assertNotNull($this->testUser->object()->getCurrentCustomer(), 'User should have current customer');

        // Debug: Check if the permission exists in the permission system at all
        $permissions = $this->currentUserService->getPermissions();
        $allPermissions = $permissions->getPermissions();
        self::assertArrayHasKey($permission, $allPermissions, 'Permission should exist in permission system');
        self::assertFalse($allPermissions[$permission]->isEnabled(), 'Permission should be disabled initially');

        // Reinitialize permissions to pick up changes
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());

        // Debug: Check if permission is enabled after reinitialization
        $permissionsAfter = $this->currentUserService->getPermissions();
        $allPermissionsAfter = $permissionsAfter->getPermissions();
        $isEnabledAfter = $allPermissionsAfter[$permission]->isEnabled();
        self::assertTrue($isEnabledAfter, 'Permission should be enabled after reinitialization');

        // Act & Assert - User should now have this admin permission
        $result = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertTrue($result, 'User-specific permission should override role-based denial');
    }

    public function testRemovedUserSpecificPermissionRevokesAccess(): void
    {
        // Arrange
        $permission = 'area_manage_orgadata';

        // Grant user-specific permission
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Verify permission is granted
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());
        $grantedResult = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertTrue($grantedResult, 'Permission should be granted initially');

        // Remove user-specific permission
        $this->userAccessControlService->removeUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Reinitialize permissions
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());

        // Act & Assert - User should no longer have this permission
        $result = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertFalse($result, 'Permission should be revoked after removing user-specific access');
    }

    public function testUserSpecificPermissionsDoNotAffectOtherUsers(): void
    {
        // Arrange
        $permission = 'area_manage_orgadata';

        // Create a separate orga and customer for the other user to ensure complete isolation
        $otherCustomer = CustomerFactory::createOne();
        $otherOrga = OrgaFactory::createOne();
        $otherUser = UserFactory::createOne();

        // Set up separate bidirectional relationship for otherUser with their own orga
        $otherUser->object()->setOrga($otherOrga->object());
        $otherOrga->object()->addUser($otherUser->object());
        $otherUser->object()->addDplanRole($this->testRole);

        // Create separate OrgaType and establish customer relationship for other user
        $otherOrgaType = new OrgaType();
        $otherOrgaType->setName(OrgaType::MUNICIPALITY);
        $otherOrgaType->setLabel('Other Municipality Label');
        $this->getEntityManager()->persist($otherOrgaType);
        $this->getEntityManager()->flush();

        // Use the addCustomerAndOrgaType method to establish the relationship properly
        $otherOrga->object()->addCustomerAndOrgaType($otherCustomer->object(), $otherOrgaType);

        // Set the current customer for the other user
        $otherUser->object()->setCurrentCustomer($otherCustomer->object());

        // Persist all changes
        $this->getEntityManager()->persist($otherUser->object());
        $this->getEntityManager()->persist($otherOrga->object());
        $this->getEntityManager()->flush();

        // Verify both users don't have the permission initially
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());
        $initialTestUserResult = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertFalse($initialTestUserResult, 'Test user should not have permission initially');

        $this->logIn($otherUser->object());
        $this->ensurePermissionsInitialized($otherUser->object());
        $initialOtherUserResult = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertFalse($initialOtherUserResult, 'Other user should not have permission initially');

        // Grant permission to testUser only
        $this->userAccessControlService->createUserPermission(
            $this->testUser->object(),
            $permission,
            $this->testRole
        );

        // Check testUser has permission
        $this->logIn($this->testUser->object());
        $this->ensurePermissionsInitialized($this->testUser->object());
        $testUserResult = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertTrue($testUserResult, 'Test user should have the permission');

        // Check otherUser does not have permission
        $this->logIn($otherUser->object());
        $this->ensurePermissionsInitialized($otherUser->object());

        // Debug - Check if otherUser has any user-specific permissions
        $otherUserPermissions = $this->userAccessControlService->getUserPermissions($otherUser->object());
        self::assertEmpty($otherUserPermissions, 'Other user should have no user-specific permissions');

        // Debug - Direct check with service
        $directCheck = $this->userAccessControlService->userPermissionExists(
            $otherUser->object(),
            $permission,
            $this->testRole
        );
        self::assertFalse($directCheck, 'Direct service check should return false for other user');

        // Debug - Check if this permission is granted by default to the role for other user
        $permissionObj = $this->currentUserService->getPermissions()->getPermission($permission);
        if ($permissionObj) {
            self::assertFalse($permissionObj->isEnabled(), 'Permission should not be enabled by default for PUBLIC_AGENCY_WORKER - it was: '.($permissionObj->isEnabled() ? 'enabled' : 'disabled'));
        }

        $otherUserResult = $this->currentUserService->getPermissions()->hasPermission($permission);
        self::assertFalse($otherUserResult, 'Other user should not have the permission');
    }
}
