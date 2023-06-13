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

use DemosEurope\DemosplanAddon\Logic\ResourceChange;

/**
 * @template O of \DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface
 */
class BeforeResourceUpdateFlushEvent extends DPlanEvent
{
    /**
     * @var ResourceChange<O>
     */
    private $resourceChange;

    /**
     * @param ResourceChange<O> $resourceChange
     */
    public function __construct(ResourceChange $resourceChange)
    {
        $this->resourceChange = $resourceChange;
    }

    /**
     * @return ResourceChange<O>
     */
    public function getResourceChange(): ResourceChange
    {
        return $this->resourceChange;
    }
}
