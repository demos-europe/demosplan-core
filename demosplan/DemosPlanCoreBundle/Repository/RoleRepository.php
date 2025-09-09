<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableObjectInterface;
use Exception;

use function in_array;

/**
 * @template-extends FluentRepository<Role>
 */
class RoleRepository extends FluentRepository implements ImmutableObjectInterface
{
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    public function addObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param string[] $ids
     *
     * @return Role[]
     */
    public function getUserRolesByIds(array $ids): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('role')
            ->from(Role::class, 'role')
            ->where('role.ident IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param string[] $codes
     *
     * @return Role[]
     */
    public function getUserRolesByCodes(array $codes): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('role')
            ->from(Role::class, 'role')
            ->where('role.code IN (:codes)')
            ->setParameter('codes', $codes)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param string[] $groupCodes
     *
     * @return Role[]
     *
     * @throws Exception
     */
    public function getUserRolesByGroupCodes(array $groupCodes): array
    {
        return $this->createQueryBuilder('role')
            ->where('role.groupCode IN (:groupCodes)')
            ->setParameter('groupCodes', array_values($groupCodes))
            ->getQuery()
            ->getResult();
    }

    public function getOrgaTypeString(Role $role, string $default = OrgaType::DEFAULT): string
    {
        foreach (OrgaType::ORGATYPE_ROLE as $orgaType => $roles) {
            if (in_array($role->getCode(), $roles, true)) {
                return $orgaType;
            }
        }

        return $default;
    }
}
