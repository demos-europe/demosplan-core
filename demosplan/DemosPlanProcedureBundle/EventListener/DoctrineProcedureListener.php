<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanProcedureBundle\Logic\PhasePermissionsetLoader;

class DoctrineProcedureListener
{
    public function __construct(private readonly PhasePermissionsetLoader $permissionsetLoader)
    {
    }

    public function postLoad(Procedure $procedure): void
    {
        $this->permissionsetLoader->loadPhasePermissionsets($procedure);
    }
}
