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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use demosplan\DemosPlanCoreBundle\Repository\AccessControlRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
// @SuppressWarnings(php:S1448) - Class has logical cohesion despite method count
class AccessControlService
{
    public const CREATE_PROCEDURES_PERMISSION = 'feature_admin_new_procedure';

    public function __construct(
        private readonly AccessControlRepository $accessControlPermissionRepository,
        private readonly RoleHandler $roleHandler,
        private readonly OrgaService $orgaService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createPermissions(string $permissionName, OrgaInterface $orga, CustomerInterface $customer, array $roles)
    {
        foreach ($roles as $role) {
            $this->createPermission($permissionName, $orga, $customer, $role);
        }
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

            if (!$role instanceof RoleInterface) {
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

        if ($role instanceof RoleInterface) {
            $criteria['role'] = [$role];
        }

        if ($orga instanceof OrgaInterface) {
            $criteria['organisation'] = [$orga];
        }

        if ($customer instanceof CustomerInterface) {
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

    public function removePermissions(string $permissionName, OrgaInterface $orga, CustomerInterface $customer, array $roles): void
    {
        foreach ($roles as $role) {
            $this->removePermission($permissionName, $orga, $customer, $role);
        }
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

        return [] !== $permissions;
    }

    public function addPermissionToGivenRole(OrgaInterface $orga, CustomerInterface $customer, string $roleName): void
    {
        $role = $this->roleHandler->getRoleByCode($roleName);

        if (!$role instanceof RoleInterface) {
            return;
        }

        $this->createPermission(self::CREATE_PROCEDURES_PERMISSION, $orga, $customer, $role);
    }

    public function removePermissionToGivenRole(OrgaInterface $orga, CustomerInterface $customer, string $roleName): void
    {
        $role = $this->roleHandler->getRoleByCode($roleName);

        if (!$role instanceof RoleInterface) {
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
            if ($this->permissionExist($permissionToEnable, $orgaToProcess, $customer, [$role->getCode()])) {
                continue;
            }

            // do not store permission for default citizen organisation
            if ($orgaToProcess->isDefaultCitizenOrganisation()) {
                continue;
            }

            $updatedOrga = $this->addPermissionBasedOnOrgaType($permissionToEnable, $role, $orgaToProcess, $customer, $dryRun);

            if ($updatedOrga instanceof OrgaInterface) {
                $updatedOrgas[] = $updatedOrga;
            }
        }

        return $updatedOrgas;
    }

    private function addPermissionBasedOnOrgaType(string $permissionToEnable, RoleInterface $role, OrgaInterface $orgaInCustomer, CustomerInterface $customer, bool $dryRun): ?OrgaInterface
    {
        $orgaTypesInCustomer = $orgaInCustomer->getTypes($customer->getSubdomain(), true);
        $canAddPermission = $this->canAddPermissionToOrgaType($permissionToEnable, $role, $orgaTypesInCustomer);

        if ($canAddPermission && !$dryRun) {
            $this->createPermission($permissionToEnable, $orgaInCustomer, $customer, $role);
        }

        return $canAddPermission ? $orgaInCustomer : null;
    }

    private function canAddPermissionToOrgaType(string $permissionToEnable, RoleInterface $role, array $orgaTypesInCustomer): bool
    {
        foreach ($orgaTypesInCustomer as $orgaTypeInCustomer) {
            if ($this->shouldSkipPermissionForOrgaType($permissionToEnable, $role, $orgaTypeInCustomer)) {
                continue;
            }

            if ($this->isRoleAllowedForOrgaType($role, $orgaTypeInCustomer)) {
                return true;
            }
        }

        return false;
    }

    private function shouldSkipPermissionForOrgaType(string $permissionToEnable, RoleInterface $role, string $orgaTypeInCustomer): bool
    {
        return self::CREATE_PROCEDURES_PERMISSION === $permissionToEnable
            && OrgaTypeInterface::PLANNING_AGENCY === $orgaTypeInCustomer
            && RoleInterface::PRIVATE_PLANNING_AGENCY !== $role->getCode();
    }

    private function isRoleAllowedForOrgaType(RoleInterface $role, string $orgaTypeInCustomer): bool
    {
        return !array_key_exists($orgaTypeInCustomer, OrgaTypeInterface::ORGATYPE_ROLE)
            || in_array($role->getCode(), OrgaTypeInterface::ORGATYPE_ROLE[$orgaTypeInCustomer], true);
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
     * @param string|null       $orgaId   Optional organization ID to restrict to a specific organization
     *
     * @return OrgaInterface[] Array of organizations to process
     */
    private function getOrganizationsForProcessing(CustomerInterface $customer, ?string $orgaId): array
    {
        return null !== $orgaId
            ? $this->getOrganizationsToProcess($customer, $orgaId)
            : $this->orgaService->getOrgasInCustomer($customer);
    }

    /**
     * Get the list of organizations to process based on the provided orgaId parameter.
     *
     * @param CustomerInterface $customer The customer to get organizations from
     * @param string|null       $orgaId   Optional organization ID to restrict to a specific organization
     *
     * @return OrgaInterface[] Array of organizations to process
     *
     * @throws InvalidArgumentException If orgaId is provided but organization is not found or doesn't belong to customer
     */
    private function getOrganizationsToProcess(CustomerInterface $customer, ?string $orgaId = null): array
    {
        if (null === $orgaId) {
            return $this->orgaService->getOrgasInCustomer($customer);
        }

        $specificOrga = $this->validateAndGetOrganization($orgaId);
        $matchingOrga = $this->findMatchingOrgaInCustomer($specificOrga, $customer);

        return [$matchingOrga];
    }

    private function validateAndGetOrganization(string $orgaId): OrgaInterface
    {
        $specificOrga = $this->orgaService->getOrga($orgaId);

        if (!$specificOrga instanceof Orga) {
            throw new InvalidArgumentException(sprintf('Organization with ID "%s" not found', $orgaId));
        }

        return $specificOrga;
    }

    private function findMatchingOrgaInCustomer(OrgaInterface $specificOrga, CustomerInterface $customer): OrgaInterface
    {
        $orgasInCustomer = $this->orgaService->getOrgasInCustomer($customer);

        foreach ($orgasInCustomer as $orgaInCustomer) {
            if ($orgaInCustomer->getId() === $specificOrga->getId()) {
                return $orgaInCustomer;
            }
        }

        throw new InvalidArgumentException(sprintf('Organization "%s" does not belong to customer "%s"', $specificOrga->getId(), $customer->getId()));
    }
}
