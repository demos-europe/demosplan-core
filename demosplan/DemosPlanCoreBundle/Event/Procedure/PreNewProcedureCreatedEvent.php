<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Events\PreNewProcedureCreatedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Event\EventConcernTrait;

class PreNewProcedureCreatedEvent extends DPlanEvent implements PreNewProcedureCreatedEventInterface
{
    use EventConcernTrait;

    /** @var array */
    protected $procedureData;

    public function __construct(array $procedureData)
    {
        $this->procedureData = $procedureData;
    }

    public function getProcedureData(): array
    {
        return $this->procedureData;
    }

    public function setProcedureData(array $procedureData)
    {
        $this->procedureData = $procedureData;
    }
}
