<?php

declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Permission;

use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControlPermission;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
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

}
