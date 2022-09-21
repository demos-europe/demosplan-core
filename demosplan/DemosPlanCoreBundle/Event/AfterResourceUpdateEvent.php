<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;

/**
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
 */
class AfterResourceUpdateEvent extends DPlanEvent
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
