<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementDeleteEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class StatementDeleteEvent extends DPlanEvent implements StatementDeleteEventInterface
{
    protected StatementInterface $statement;

    public function __construct(StatementInterface $statement)
    {
        $this->statement = $statement;
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }
}
