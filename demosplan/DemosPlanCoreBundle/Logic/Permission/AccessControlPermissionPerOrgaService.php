<?php

declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Permission;

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

class AccessControlPermissionPerOrgaService extends CoreService
{
    public function __construct(
        private readonly AccessControlPermissionRepository $accessControlPermissionRepository,
        private readonly RoleHandler $roleHandler,

    ) {
    }

    public function createPermissionForOrga($permissionName, $orga, $customer, $role): AccessControlPermission
    {
        $permission = new AccessControlPermission();
        $permission->setPermission($permissionName);
        $permission->setOrga($orga);
        $permission->setCustomer($customer);
        $permission->setRole($role);
        $this->accessControlPermissionRepository->add($permission);

        return $permission;
    }

    public function getPermissionForOrga($orga, $customer, $roles): array
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
                'organisation' => $orga,
                'customer' => $customer,
                'role' => $role
            ]);


            // Loop through each permission object and get the permission name
            foreach ($permissions as $permissionObject) {
                $enabledPermissions[] = $permissionObject->getPermission();
            }
        }


        // Return the permissions array
        return $enabledPermissions;
    }

}
