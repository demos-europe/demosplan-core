<?php

namespace demosplan\DemosPlanCoreBundle\Event\Statement;


use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\DeleteEmailImportedStatementEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class DeleteEmailImportedStatementEvent extends DPlanEvent implements DeleteEmailImportedStatementEventInterface
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
