<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\AfterSegmentationEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AfterSegmentationEvent extends DPlanEvent implements AfterSegmentationEventInterface
{
    public function __construct(private Statement $statement)
    {
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function setStatement(Statement $statement): void
    {
        $this->statement = $statement;
    }
}
