<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\RecommendationRequestEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class RecommendationRequestEvent extends DPlanEvent implements RecommendationRequestEventInterface
{
    public function __construct(
        protected readonly StatementInterface $statement,
        protected readonly ProcedureInterface $procedure,
    ) {
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }
}
