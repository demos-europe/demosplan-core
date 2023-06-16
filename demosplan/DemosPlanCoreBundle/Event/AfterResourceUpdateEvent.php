<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use DemosEurope\DemosplanAddon\Contracts\Events\AfterResourceUpdateEventInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;

/**
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
 */
class AfterResourceUpdateEvent extends DPlanEvent implements AfterResourceUpdateEventInterface
{
    /**
     * @param ResourceChange<O> $resourceChange
     */
    public function __construct(private readonly ResourceChange $resourceChange)
    {
    }

    /**
     * @return ResourceChange<O>
     */
    public function getResourceChange(): ResourceChange
    {
        return $this->resourceChange;
    }
}
