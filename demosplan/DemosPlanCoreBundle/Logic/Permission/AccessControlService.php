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
use InvalidArgumentException;

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

    public function enablePermissionCustomerOrgaRole(string $permissionToEnable, CustomerInterface $customer, RoleInterface $role, bool $dryRun = false, ?string $orgaId = null): array
    {
        $organizationsToProcess = $this->getOrganizationsForProcessing($customer, $orgaId);
        
        $updatedOrgas = [];

        foreach ($organizationsToProcess as $orgaToProcess) {
            // If permission is already stored, skip it
            if (true === $this->permissionExist($permissionToEnable, $orgaToProcess, $customer, [$role->getCode()])) {
                continue;
            }

            // do not store permission for default citizen organisation
            if ($orgaToProcess->isDefaultCitizenOrganisation()) {
                continue;
            }

            $updatedOrga = $this->addPermissionBasedOnOrgaType($permissionToEnable, $role, $orgaToProcess, $customer, $dryRun);

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

    public function disablePermissionCustomerOrgaRole(string $permissionToEnable, CustomerInterface $customer, RoleInterface $role, bool $dryRun = false, ?string $orgaId = null): array
    {
        $organizationsToProcess = $this->getOrganizationsForProcessing($customer, $orgaId);
        
        $updatedOrgas = [];

        foreach ($organizationsToProcess as $orgaToProcess) {
            // If permission is not stored, skip it
            if (false === $this->permissionExist($permissionToEnable, $orgaToProcess, $customer, [$role->getCode()])) {
                continue;
            }

            // Do not remove permission if it is dryrun
            if (false === $dryRun) {
                $this->removePermission($permissionToEnable, $orgaToProcess, $customer, $role);
            }

            // Save the impacted orga in the array
            $updatedOrgas[] = $orgaToProcess;
        }

        return $updatedOrgas;
    }

    /**
     * Get organizations for processing based on optional organization ID.
     * 
     * @param CustomerInterface $customer The customer to get organizations from
     * @param string|null $orgaId Optional organization ID to restrict to a specific organization
     * @return OrgaInterface[] Array of organizations to process
     */
    private function getOrganizationsForProcessing(CustomerInterface $customer, ?string $orgaId): array
    {
        return $orgaId !== null 
            ? $this->getOrganizationsToProcess($customer, $orgaId)
            : $this->orgaService->getOrgasInCustomer($customer);
    }

    /**
     * Get the list of organizations to process based on the provided orgaId parameter.
     * 
     * @param CustomerInterface $customer The customer to get organizations from
     * @param string|null $orgaId Optional organization ID to restrict to a specific organization
     * @return OrgaInterface[] Array of organizations to process
     * @throws InvalidArgumentException If orgaId is provided but organization is not found or doesn't belong to customer
     */
    private function getOrganizationsToProcess(CustomerInterface $customer, ?string $orgaId = null): array
    {
        // If specific organization ID is provided, validate and return only that organization
        if (null !== $orgaId) {
            $specificOrga = $this->orgaService->getOrga($orgaId);
            if (null === $specificOrga) {
                throw new InvalidArgumentException(sprintf('Organization with ID "%s" not found', $orgaId));
            }

            // Verify the organization belongs to the customer and get the properly managed entity
            $orgasInCustomer = $this->orgaService->getOrgasInCustomer($customer);
            $matchingOrga = null;
            foreach ($orgasInCustomer as $orgaInCustomer) {
                if ($orgaInCustomer->getId() === $specificOrga->getId()) {
                    $matchingOrga = $orgaInCustomer;
                    break;
                }
            }

            if (null === $matchingOrga) {
                throw new InvalidArgumentException(sprintf('Organization "%s" does not belong to customer "%s"', $orgaId, $customer->getId()));
            }

            return [$matchingOrga];
        }

        // Return all organizations in the customer
        return $this->orgaService->getOrgasInCustomer($customer);
    }
}
