<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ProcedurePhaseDefinitionMarkedAsDeletedEventInterface;

class ProcedurePhaseDefinitionMarkedAsDeletedEvent extends DPlanEvent implements ProcedurePhaseDefinitionMarkedAsDeletedEventInterface
{
    public function __construct(private readonly ProcedurePhaseDefinitionInterface $phaseDefinition)
    {
    }

    public function getPhaseDefinition(): ProcedurePhaseDefinitionInterface
    {
        return $this->phaseDefinition;
    }
}
