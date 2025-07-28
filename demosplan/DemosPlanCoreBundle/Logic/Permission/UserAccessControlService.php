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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\UserAccessControlServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\UserAccessControlRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserAccessControlService extends CoreService implements UserAccessControlServiceInterface
{
    public function __construct(
        private readonly UserAccessControlRepository $userAccessControlRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createUserPermission(
        UserInterface $user,
        string $permission,
        ?RoleInterface $role = null,
    ): UserAccessControl {
        $this->validateUserPermissionRequest($user, $permission, $role);

        $orga = $user->getOrga();
        $customer = $user->getCurrentCustomer();

        // Validate that user has proper orga/customer setup
        if (null === $orga || null === $customer) {
            throw new InvalidArgumentException('User must have a valid organization with a current customer');
        }

        // Use the first role if none specified
        $role = $role ?? $user->getDplanRoles()->first();

        if (null === $role || false === $role) {
            throw new InvalidArgumentException('User must have at least one role');
        }

        // Check if permission already exists
        if ($this->userPermissionExists($user, $permission, $role)) {
            // Return existing permission instead of creating duplicate
            return $this->userAccessControlRepository->findOneBy([
                'user'         => $user,
                'organisation' => $orga,
                'customer'     => $customer,
                'role'         => $role,
                'permission'   => $permission,
            ]);
        }

        // Get fresh instances from database to ensure they're managed by EntityManager
        $customer = $this->entityManager->find(get_class($customer), $customer->getId());
        $role = $this->entityManager->find(get_class($role), $role->getId());
        $orga = $this->entityManager->find(get_class($orga), $orga->getId());

        if (null === $customer || null === $role || null === $orga) {
            throw new InvalidArgumentException('Unable to find required entities in database');
        }

        $userPermission = new UserAccessControl();
        $userPermission->setUser($user);
        $userPermission->setOrganisation($orga);
        $userPermission->setCustomer($customer);
        $userPermission->setRole($role);
        $userPermission->setPermission($permission);

        $this->entityManager->persist($userPermission);
        $this->entityManager->flush();

        return $userPermission;
    }

    public function removeUserPermission(
        UserInterface $user,
        string $permission,
        ?RoleInterface $role = null,
    ): bool {
        $orga = $user->getOrga();
        $customer = $user->getCurrentCustomer();

        // Return false if user doesn't have proper orga/customer setup
        if (null === $orga || null === $customer) {
            return false;
        }

        $role = $role ?? $user->getDplanRoles()->first();

        // Return false if user has no roles
        if (null === $role || false === $role) {
            return false;
        }

        $userPermission = $this->userAccessControlRepository->findOneBy([
            'user'         => $user,
            'organisation' => $orga,
            'customer'     => $customer,
            'role'         => $role,
            'permission'   => $permission,
        ]);

        if ($userPermission) {
            $this->entityManager->remove($userPermission);
            $this->entityManager->flush();

            return true;
        }

        return false;
    }

    public function getUserPermissions(UserInterface $user): array
    {
        $orga = $user->getOrga();
        $customer = $user->getCurrentCustomer(); // Use same method as Permissions class

        // Return empty array if user doesn't have proper orga/customer setup
        if (null === $orga || null === $customer) {
            return [];
        }

        return $this->userAccessControlRepository->getPermissionsByUserAndRoles(
            $user,
            $orga,
            $customer,
            $user->getRoles() // Use same method as Permissions class
        );
    }

    public function userPermissionExists(
        UserInterface $user,
        string $permission,
        ?RoleInterface $role = null,
    ): bool {
        $orga = $user->getOrga();
        $customer = $user->getCurrentCustomer();

        // Return false if user doesn't have proper orga/customer setup
        if (null === $orga || null === $customer) {
            return false;
        }

        $role = $role ?? $user->getDplanRoles()->first();

        // Return false if user has no roles
        if (null === $role || false === $role) {
            return false;
        }

        return $this->userAccessControlRepository->permissionExists(
            $user,
            $orga,
            $customer,
            $role,
            $permission
        );
    }

    /**
     * Validate a user permission request.
     *
     * @throws InvalidArgumentException
     */
    private function validateUserPermissionRequest(
        UserInterface $user,
        string $permission,
        ?RoleInterface $role,
    ): void {
        // Validate user has required relationships
        if (null === $user->getOrga()) {
            throw new InvalidArgumentException('User must have an organization');
        }

        if (null === $user->getCurrentCustomer()) {
            throw new InvalidArgumentException('User organization must have a customer');
        }

        // Validate that role belongs to user if specified (compare by code, not object identity)
        if ($role) {
            $userRoleCodes = $user->getDplanRoles()->map(fn ($r) => $r->getCode())->toArray();
            if (!in_array($role->getCode(), $userRoleCodes, true)) {
                throw new InvalidArgumentException('User does not have the specified role');
            }
        }

        // Validate permission is not empty
        if (empty(trim($permission))) {
            throw new InvalidArgumentException('Permission cannot be empty');
        }
    }
}
