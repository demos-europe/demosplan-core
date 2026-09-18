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
use DemosEurope\DemosplanAddon\Contracts\Events\AssessableStatementDeletedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AssessableStatementDeletedEvent extends DPlanEvent implements AssessableStatementDeletedEventInterface
{
    public function __construct(
        protected string $statementId,
        protected string $externId,
        protected ProcedureInterface $procedure,
        protected bool $wasSegmented,
    ) {
    }

    public function getStatementId(): string
    {
        return $this->statementId;
    }

    public function getExternId(): string
    {
        return $this->externId;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }

    public function wasSegmented(): bool
    {
        return $this->wasSegmented;
    }
}
