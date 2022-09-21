<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use demosplan\DemosPlanCoreBundle\Entity\User\User;

abstract class ProjectPermissions extends Permissions implements ProjectPermissionsInterface
{
    public function initPermissions(User $user, array $context = null): Permissions
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
