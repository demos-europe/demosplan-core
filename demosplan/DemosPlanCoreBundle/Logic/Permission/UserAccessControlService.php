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
        if (!$orga instanceof OrgaInterface || !$customer instanceof CustomerInterface) {
            throw new InvalidArgumentException('User must have a valid organization with a current customer');
        }

        // Use the first role if none specified
        $role ??= $user->getDplanRoles()->first();

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
        $customer = $this->entityManager->find($customer::class, $customer->getId());
        $role = $this->entityManager->find($role::class, $role->getId());
        $orga = $this->entityManager->find($orga::class, $orga->getId());

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
        if (!$orga instanceof OrgaInterface || !$customer instanceof CustomerInterface) {
            return false;
        }

        $conditions = [
            'user'         => $user,
            'organisation' => $orga,
            'customer'     => $customer,
            'permission'   => $permission,
        ];
        if ($role instanceof RoleInterface) {
            $conditions['role'] = $role;
        }
        $userPermission = $this->userAccessControlRepository->findOneBy($conditions);

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
        if (!$orga instanceof OrgaInterface || !$customer instanceof CustomerInterface) {
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
        if (!$orga instanceof OrgaInterface || !$customer instanceof CustomerInterface) {
            return false;
        }

        return $this->userAccessControlRepository->permissionExists(
            $permission,
            $user,
            $orga,
            $customer,
            $role
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
        if (!$user->getOrga() instanceof OrgaInterface) {
            throw new InvalidArgumentException('User must have an organization');
        }

        if (!$user->getCurrentCustomer() instanceof CustomerInterface) {
            throw new InvalidArgumentException('User organization must have a customer');
        }

        // Validate that role belongs to user if specified (compare by code, not object identity)
        if ($role instanceof RoleInterface) {
            $userRoleCodes = $user->getDplanRoles()->map(fn ($r) => $r->getCode())->toArray();
            if (!in_array($role->getCode(), $userRoleCodes, true)) {
                throw new InvalidArgumentException('User does not have the specified role');
            }
        }

        // Validate permission is not empty
        if (in_array(trim($permission), ['', '0'], true)) {
            throw new InvalidArgumentException('Permission cannot be empty');
        }
    }
}
