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
        $permission->setPermission($permissionName);
        $permission->setOrga($orga);
        $permission->setCustomer($customer);
        $permission->setRole($role);
        $this->accessControlPermissionRepository->add($permission);

        return $permission;
    }

    public function getPermission($orga, $customer, $roles): array
    {
        // Split the roles string into an array
        $rolesArray = explode(',', $roles);

        // Initialize an array to hold the permissions
        $enabledPermissions = [];

        // Loop through each role
        foreach ($rolesArray as $roleName) {
            // Try to find an existing permission with the given parameters
            $role = $this->roleHandler->getUserRolesByCodes([$roleName])[0];

            $permissions = $this->accessControlPermissionRepository->findBy([
                'organisation' => [$orga, null],
                'customer'     => [$customer, null],
                'role'         => [$role, null],
            ]);

            // Loop through each permission object and get the permission name
            foreach ($permissions as $permissionObject) {
                $enabledPermissions[] = $permissionObject->getPermission();
            }
        }

        // Return the permissions array
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
     * @param OrgaInterface     $orga
     * @param CustomerInterface $orga
     * @param string            $roles
     */
    public function canCreateProcedure($orga, $customer, $roles): bool
    {
        // Check if the user has the permission to create a procedure
        $permissions = $this->getPermission($orga, $customer, $roles);

        return in_array('feature_admin_new_procedure', $permissions);
    }
}
