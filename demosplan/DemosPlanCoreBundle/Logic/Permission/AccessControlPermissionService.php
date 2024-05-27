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
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\AccessControlPermissionRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
class AccessControlPermissionService extends CoreService
{
    public const CREATE_PROCEDURES_PERMISSION = 'feature_admin_new_procedure';

    public function __construct(
        private readonly AccessControlPermissionRepository $accessControlPermissionRepository,
        private readonly RoleHandler $roleHandler,
        private readonly GlobalConfig $globalConfig
    ) {
    }

    public function createPermission(string $permissionName, ?OrgaInterface $orga, ?CustomerInterface $customer, ?RoleInterface $role): AccessControlPermission
    {
        $permission = new AccessControlPermission();
        $permission->setPermissionName($permissionName);
        $permission->setOrga($orga);
        $permission->setCustomer($customer);
        $permission->setRole($role);
        $this->accessControlPermissionRepository->add($permission);

        return $permission;
    }

    public function getPermissions(?OrgaInterface $orga, ?CustomerInterface $customer, array $roles): array
    {
        // Initialize an array to hold the permissions
        $enabledPermissions = [];

        // Loop through each role
        foreach ($roles as $roleName) {
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
            if($this->checkIfPermissionIsAvailableForOrgaType($permissionObject->getPermissionName(), $orga)) {
                $enabledPermissions[] = $permissionObject->getPermissionName();
            }
        }

        return $enabledPermissions;
    }

    private function removePermission(string $permissionName, OrgaInterface $orga, CustomerInterface $customer, RoleInterface $role): void
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

    public function hasPermission(string $permissionToCheck, ?OrgaInterface $orga = null, ?CustomerInterface $customer = null, ?array $roleCodes = null): bool
    {
        // Loop through each role
        $permissions = [];

        if (null !== $roleCodes) {
            foreach ($roleCodes as $roleName) {
                // Try to find an existing permission with the given parameters
                $role = $this->roleHandler->getUserRolesByCodes([$roleName])[0];
                $permissions = $this->getEnabledPermissionNames($role, $orga, $customer, $permissionToCheck);
            }
        } else {
            $permissions = $this->getEnabledPermissionNames(null, $orga, $customer, $permissionToCheck);
        }

        return !empty($permissions);
    }

    /**
     * @param CustomerInterface $orga
     */
    public function grantCanCreateProcedurePermission(?OrgaInterface $orga = null, ?CustomerInterface $customer = null, ?RoleInterface $role): bool
    {
        // Get the available permissions for the organization type

        if (null === $orga) {
            $this->createPermission(self::CREATE_PROCEDURES_PERMISSION, $orga, $customer, $role);
            return true;
        }
        if ($this->checkIfPermissionIsAvailableForOrgaType(self::CREATE_PROCEDURES_PERMISSION , $orga)) {
            $this->createPermission(self::CREATE_PROCEDURES_PERMISSION, $orga, $customer, $role);
            return true;
        }
        return false;

    }

    /**
     * @param CustomerInterface $orga
     */
    public function revokeCanCreateProcedurePermission(?OrgaInterface $orga = null, ?CustomerInterface $customer = null, ?RoleInterface $role): void
    {
        $this->removePermission(self::CREATE_PROCEDURES_PERMISSION, $orga, $customer, $role);
    }

    private function checkIfPermissionIsAvailableForOrgaType(string $permission, ?OrgaInterface $orga) : bool {
        //It will be granted because it is not checking on orga level
        if (null === $orga) {
            return true;
        }

        $availablePermissions = $this->getAvailablePermissionsPerOrgaType($orga);
        return in_array($permission, $availablePermissions);
    }

    private function getAvailablePermissionsPerOrgaType(OrgaInterface $orga) : array {
        $availablePermissions = [];
        if ($orga->hasType(OrgaType::PLANNING_AGENCY, $this->globalConfig->getSubdomain())) {
            $availablePermissions[] = self::CREATE_PROCEDURES_PERMISSION;
        }
        return $availablePermissions;
    }
}
