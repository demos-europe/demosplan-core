<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanProcedureBundle\Logic\PhasePermissionsetLoader;

class DoctrineProcedureListener
{
    /**
     * @var PhasePermissionsetLoader
     */
    private $permissionsetLoader;

    public function __construct(PhasePermissionsetLoader $permissionsetLoader)
    {
        $this->permissionsetLoader = $permissionsetLoader;
    }

    public function postLoad(Procedure $procedure): void
    {
        $this->permissionsetLoader->loadPhasePermissionsets($procedure);
    }
}
