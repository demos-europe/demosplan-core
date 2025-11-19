<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;

class UserAccessControlRepository extends CoreRepository
{
    /**
     * Get permissions by user and roles with organization and customer filtering.
     * Roles can be passed as either role names (strings) or Role entities.
     *
     * @param array $roles Array of role names (strings) or Role entities
     *
     * @return UserAccessControl[]
     */
    public function getPermissionsByUserAndRoles(
        UserInterface $user,
        OrgaInterface $orga,
        CustomerInterface $customer,
        array $roles,
    ): array {
        // If roles are strings (role names), convert them to Role entities
        if ([] !== $roles && is_string($roles[0])) {
            $roleEntities = $this->getEntityManager()
                ->getRepository(Role::class)
                ->createQueryBuilder('r')
                ->where('r.code IN (:roleCodes)')
                ->setParameter('roleCodes', $roles)
                ->getQuery()
                ->getResult();
            $roles = $roleEntities;
        }

        return $this->createQueryBuilder('uac')
            ->where('uac.user = :user')
            ->andWhere('uac.organisation = :orga')
            ->andWhere('uac.customer = :customer')
            ->andWhere('uac.role IN (:roles)')
            ->setParameter('user', $user)
            ->setParameter('orga', $orga)
            ->setParameter('customer', $customer)
            ->setParameter('roles', $roles)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all permissions for a specific user.
     *
     * @return UserAccessControl[]
     */
    public function findByUser(UserInterface $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    /**
     * Check if a specific permission exists for a user.
     */
    public function permissionExists(
        string $permission,
        UserInterface $user,
        OrgaInterface $orga,
        CustomerInterface $customer,
        ?RoleInterface $role = null,
    ): bool {
        $conditions = [
            'user'         => $user,
            'organisation' => $orga,
            'customer'     => $customer,
            'permission'   => $permission,
        ];
        if ($role instanceof RoleInterface) {
            $conditions['role'] = $role;
        }

        return null !== $this->findOneBy($conditions);
    }
}
