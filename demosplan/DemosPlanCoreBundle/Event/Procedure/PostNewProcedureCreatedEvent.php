<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Events\PostNewProcedureCreatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Event\EventConcernTrait;

class PostNewProcedureCreatedEvent extends DPlanEvent implements PostNewProcedureCreatedEventInterface
{
    use EventConcernTrait;

    /** @var Procedure */
    protected $procedure;

    public function __construct(Procedure $procedure, /**
     * Identifies a ProcedureCoupleToken, to allow to couple the procedures.
     */
        private readonly ?string $token = null,
        private readonly string $usedBluePrintId)
    {
        $this->procedure = $procedure;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getUsedBluePrintId(): string
    {
        return $this->usedBluePrintId;
    }
}
