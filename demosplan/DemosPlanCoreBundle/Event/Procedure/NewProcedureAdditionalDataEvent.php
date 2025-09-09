<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class NewProcedureAdditionalDataEvent extends DPlanEvent
{
    /** @var Procedure */
    protected $procedure;

    public function __construct(Procedure $procedure)
    {
        $this->procedure = $procedure;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }
}
