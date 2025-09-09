<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\CoreApplication\Permissions;

use demosplan\DemosPlanCoreBundle\Permissions\ProjectPermissions;

class Permissions extends ProjectPermissions
{
    public function projectGlobalPermissions(): void
    {
        // None to set, we want to test the core permissions
    }

    public function projectProcedurePermissions(): void
    {
        // None to set, we want to test the core permissions
    }
}
