<?php

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

class ProcedureFileSubmitEvent extends DPlanEvent
{
    /** @var Procedure[] */
    protected $procedures = [];

    /**
     * @return Procedure[]
     */
    public function getProcedures(): array
    {
        return $this->procedures;
    }

    /**
     * @param Procedure[] $procedures
     */
    public function setProcedures(array $procedures)
    {
        $this->procedures = $procedures;
    }

    public function addProcedure(Procedure $procedure)
    {
        $this->procedures[] = $procedure;
    }
}
