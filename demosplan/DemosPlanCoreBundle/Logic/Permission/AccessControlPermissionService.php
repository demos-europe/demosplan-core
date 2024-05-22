<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Permission;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControlPermission;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\AccessControlPermissionRepository;

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
class AccessControlPermissionService extends CoreService
{
    public const CAN_CREATE_PROCEDURES = 'feature_admin_new_procedure';

    public function __construct(
        private readonly AccessControlPermissionRepository $accessControlPermissionRepository,
        private readonly RoleHandler $roleHandler,
    ) {
    }

    public function createPermission($permissionName, $orga, $customer, $role): AccessControlPermission
    {
        $permission = new AccessControlPermission();
        $permission->setPermissionName($permissionName);
        $permission->setOrga($orga);
        $permission->setCustomer($customer);
        $permission->setRole($role);
        $this->accessControlPermissionRepository->add($permission);

        return $permission;
    }

    public function getPermission(?OrgaInterface $orga, ?CustomerInterface $customer, string $roles): array
    {
        // Split the roles string into an array
        $rolesArray = explode(',', $roles);

        // Initialize an array to hold the permissions
        $enabledPermissions = [];

        // Loop through each role
        foreach ($rolesArray as $roleName) {
            // Try to find an existing permission with the given parameters
            $role = $this->roleHandler->getUserRolesByCodes([$roleName])[0];

            $permissions = $this->getEnabledPermissionNames($role, $orga, $customer, null);

            // Add the permissions to the enabledPermissions array
            array_push($enabledPermissions, ...$permissions);
        }

        // Return the permissions array
        return $enabledPermissions;
    }

    private function getEnabledPermissionNames(?RoleInterface $role, ?OrgaInterface $orga, ?CustomerInterface $customer, ?string $permissionName): array
    {
        $enabledPermissions = [];

        $criteria = [];

        if (null !== $role) {
            $criteria['role'] = [$role, null];
        }

        if (null !== $orga) {
            $criteria['organisation'] = [$orga, null];
        }

        if (null !== $customer) {
            $criteria['customer'] = [$customer, null];
        }

        if (null !== $permissionName) {
            $criteria['permission'] = $permissionName;
        }

        $permissions = $this->accessControlPermissionRepository->findBy($criteria);

        // Loop through each permission object and get the permission name
        foreach ($permissions as $permissionObject) {
            $enabledPermissions[] = $permissionObject->getPermissionName();
        }

        return $enabledPermissions;
    }

    public function removePermission($permissionName, $orga, $customer, $role): void
    {
        // Find the existing permission with the given parameters
        $permission = $this->accessControlPermissionRepository->findOneBy([
            'permission'   => $permissionName,
            'organisation' => $orga,
            'customer'     => $customer,
            'role'         => $role,
        ]);

        // If a permission is found, remove it
        if ($permission) {
            $this->accessControlPermissionRepository->persistAndDelete([], [$permission]);
        }
    }

    /**
     * @param CustomerInterface $orga
     * @param ?string           $roles
     */
    public function canCreateProcedure(?OrgaInterface $orga = null, ?CustomerInterface $customer = null, ?string $roles = null): bool
    {
        // Check if the user has the permission to create a procedure
        $permissions = $this->getEnabledPermissionNames($roles, $orga, $customer, self::CAN_CREATE_PROCEDURES);

        return !empty($permissions);
    }
}
