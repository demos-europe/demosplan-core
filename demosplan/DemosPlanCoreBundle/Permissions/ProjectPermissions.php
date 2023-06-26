<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class ProjectPermissions extends Permissions implements ProjectPermissionsInterface
{
    public function initPermissions(UserInterface $user, array $context = null): PermissionsInterface
    {
        parent::initPermissions($user, $context);

        $this->projectGlobalPermissions();

        return $this;
    }

    protected function setProcedurePermissions(): void
    {
        parent::setProcedurePermissions();

        $this->projectProcedurePermissions();
    }
}
