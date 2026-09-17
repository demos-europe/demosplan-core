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
use DemosEurope\DemosplanAddon\Contracts\Events\AssessableStatementDeletedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AssessableStatementDeletedEvent extends DPlanEvent implements AssessableStatementDeletedEventInterface
{
    public function __construct(
        protected StatementInterface $statement,
        protected bool $wasSegmented,
    ) {
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }

    public function wasSegmented(): bool
    {
        return $this->wasSegmented;
    }
}
