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
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Exception;

class AccessControlRepository extends CoreRepository implements AccessControlRepositoryInterface
{
    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(AccessControl $permission): AccessControl
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($permission);
            $em->flush();

            return $permission;
        } catch (Exception $e) {
            $this->logger->warning('Permission could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Add permission manually.
     *
     * @throws Exception
     */
    public function addManually(
        OrgaInterface $orga,
        CustomerInterface $customer,
        string $roleCode,
        string $permissionName,
    ): void {
        $role = $this->getEntityManager()->getRepository(Role::class)->findOneBy(['code' => $roleCode]);

        $permission = new AccessControl();
        $permission->setOrga($orga);
        $permission->setRole($role);
        $permission->setCustomer($customer);
        $permission->setPermissionName($permissionName);

        $this->add($permission);
    }
}
