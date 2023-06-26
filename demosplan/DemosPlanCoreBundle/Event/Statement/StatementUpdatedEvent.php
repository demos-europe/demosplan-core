<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\StatementUpdatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class StatementUpdatedEvent extends StatementActionEvent implements StatementUpdatedEventInterface
{
    /** @var Statement */
    protected $preUpdateStatement;

    public function __construct(Statement $preUpdateStatement, Statement $statement)
    {
        $this->preUpdateStatement = $preUpdateStatement;
        parent::__construct($statement);
    }

    public function getPreUpdateStatement(): Statement
    {
        return $this->preUpdateStatement;
    }

    public function setPreUpdateStatement(Statement $preUpdateStatement)
    {
        $this->preUpdateStatement = $preUpdateStatement;
    }
}
