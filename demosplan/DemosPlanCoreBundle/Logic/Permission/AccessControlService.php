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
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use demosplan\DemosPlanCoreBundle\Repository\AccessControlRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
class AccessControlService extends CoreService
{
    public const CREATE_PROCEDURES_PERMISSION = 'feature_admin_new_procedure';

    public function __construct(
        private readonly AccessControlRepository $accessControlPermissionRepository,
        private readonly RoleHandler $roleHandler,
        private readonly OrgaService $orgaService
    ) {
    }

    public function createPermission(string $permissionName, OrgaInterface $orga, CustomerInterface $customer, RoleInterface $role): ?AccessControl
    {
        try {
            $permission = new AccessControl();
            $permission->setPermissionName($permissionName);
            $permission->setOrga($orga);
            $permission->setCustomer($customer);
            $permission->setRole($role);
            $this->accessControlPermissionRepository->add($permission);

            return $permission;
        } catch (UniqueConstraintViolationException $exception) {
            $this->logger->warning('Unique constraint violation occurred while trying to create a permission.', [
                'exception'      => $exception->getMessage(),
                'permissionName' => $permissionName,
                'orga'           => $orga->getId(),
                'customer'       => $customer->getId(),
                'role'           => $role->getId(),
            ]);
        }

        return null;
    }

    public function getPermissions(?OrgaInterface $orga, ?CustomerInterface $customer, array $roles): array
    {
        // Initialize an array to hold the permissions
        $enabledPermissions = [];

        // Loop through each role
        foreach ($roles as $roleName) {
            // Try to find an existing permission with the given parameters

            $role = $this->roleHandler->getRoleByCode($roleName);

            if (null === $role) {
                continue;
            }

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
            $criteria['role'] = [$role];
        }

        if (null !== $orga) {
            $criteria['organisation'] = [$orga];
        }

        if (null !== $customer) {
            $criteria['customer'] = [$customer];
        }

        if (null !== $permissionName) {
            $criteria['permission'] = $permissionName;
        }

        $permissions = $this->accessControlPermissionRepository->findBy($criteria);

        // Loop through each permission object and get the permission name
        foreach ($permissions as $permissionObject) {
            $enabledPermissions[] = $permissionObject->getPermissionName();
        }

        // Remove duplicates
        $enabledPermissions = array_unique($enabledPermissions);

        return $enabledPermissions;
    }

    public function removePermission(string $permissionName, OrgaInterface $orga, CustomerInterface $customer, RoleInterface $role): void
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
     * Checks if the given permission is allowed for the organization type in that customer.
     *
     * Returns true if the permission is allowed for the organization type or if the permission is not 'CREATE_PROCEDURES_PERMISSION'.
     * Returns false if the permission is 'CREATE_PROCEDURES_PERMISSION' and the organization type is not 'PLANNING_AGENCY'.
     */
    public function checkPermissionForOrgaType(string $permissionToCheck, OrgaInterface $orga, CustomerInterface $customer): bool
    {
        if (self::CREATE_PROCEDURES_PERMISSION === $permissionToCheck) {
            return in_array(OrgaTypeInterface::PLANNING_AGENCY, $orga->getTypes($customer->getSubdomain(), true));
        }

        return true;
    }

    public function permissionExist(string $permissionToCheck, ?OrgaInterface $orga = null, ?CustomerInterface $customer = null, ?array $roleCodes = null): bool
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

    public function addPermissionToGivenRole(OrgaInterface $orga, CustomerInterface $customer, string $roleName): void
    {
        $role = $this->roleHandler->getRoleByCode($roleName);

        if (null === $role) {
            return;
        }

        $this->createPermission(self::CREATE_PROCEDURES_PERMISSION, $orga, $customer, $role);
    }

    public function removePermissionToGivenRole(OrgaInterface $orga, CustomerInterface $customer, string $roleName): void
    {
        $role = $this->roleHandler->getRoleByCode($roleName);

        if (null === $role) {
            return;
        }

        $this->removePermission(self::CREATE_PROCEDURES_PERMISSION, $orga, $customer, $role);
    }

    public function enablePermissionCustomerOrgaRole(string $permissionToEnable, CustomerInterface $customer, RoleInterface $role, bool $dryRun = false): array
    {
        // enable permission for all orga on the given customer and role

        $orgasInCustomer = $this->orgaService->getOrgasInCustomer($customer);
        $updatedOrgas = [];

        foreach ($orgasInCustomer as $orgaInCustomer) {
            // If permisison is already stored, skip it
            if (true === $this->permissionExist($permissionToEnable, $orgaInCustomer, $customer, [$role->getCode()])) {
                continue;
            }

            // do not store permission for default citizen organisation
            if ($orgaInCustomer->isDefaultCitizenOrganisation()) {
                continue;
            }

            $updatedOrga = $this->addPermissionBasedOnOrgaType($permissionToEnable, $role, $orgaInCustomer, $customer, $dryRun);

            if (null !== $updatedOrga) {
                $updatedOrgas[] = $updatedOrga;
            }
        }

        return $updatedOrgas;
    }

    private function addPermissionBasedOnOrgaType(string $permissionToEnable, RoleInterface $role, OrgaInterface $orgaInCustomer, CustomerInterface $customer, bool $dryRun): ?OrgaInterface
    {
        $orgaTypesInCustomer = $orgaInCustomer->getTypes($customer->getSubdomain(), true);
        foreach ($orgaTypesInCustomer as $orgaTypeInCustomer) {
            // If permission is 'CREATE_PROCEDURES_PERMISSION' and orga type is 'PLANNING_AGENCY' and role is not 'PRIVATE_PLANNING_AGENCY', skip it
            if (self::CREATE_PROCEDURES_PERMISSION === $permissionToEnable
                && OrgaTypeInterface::PLANNING_AGENCY === $orgaTypeInCustomer
                && RoleInterface::PRIVATE_PLANNING_AGENCY !== $role->getCode()) {
                continue;
            }

            // check whether role to grant the permission is allowed in the given orga type
            // to avoid e.g. granting planner permission to institution orga
            if (array_key_exists($orgaTypeInCustomer, OrgaTypeInterface::ORGATYPE_ROLE) &&
                !in_array($role->getCode(), OrgaTypeInterface::ORGATYPE_ROLE[$orgaTypeInCustomer],true)) {
                continue;
            }

            // Do not store permission if it is dryrun
            if (false === $dryRun) {
                $this->createPermission($permissionToEnable, $orgaInCustomer, $customer, $role);
            }

            // Return orga where permission was stored
            return $orgaInCustomer;
        }

        // Return null if no orga is impacted
        return null;
    }

    public function disablePermissionCustomerOrgaRole(string $permissionToEnable, CustomerInterface $customer, RoleInterface $role, bool $dryRun = false): array
    {
        $orgasInCustomer = $this->orgaService->getOrgasInCustomer($customer);
        $updatedOrgas = [];

        foreach ($orgasInCustomer as $orgaInCustomer) {
            // If permisison is already stored, skip it
            if (false === $this->permissionExist($permissionToEnable, $orgaInCustomer, $customer, [$role->getCode()])) {
                continue;
            }

            // Do not remove permission if it is dryrun
            if (false === $dryRun) {
                $this->removePermission($permissionToEnable, $orgaInCustomer, $customer, $role);
            }

            // Save the impacted orga in the array
            $updatedOrgas[] = $orgaInCustomer;
        }

        return $updatedOrgas;
    }
}
