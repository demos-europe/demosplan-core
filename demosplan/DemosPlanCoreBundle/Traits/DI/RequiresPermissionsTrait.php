<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits\DI;

use demosplan\DemosPlanCoreBundle\Permissions\Permissions;

trait RequiresPermissionsTrait
{
    /**
     * @var Permissions
     */
    protected $permissions;

    /**
     * @return Permissions
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @param Permissions $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }
}
