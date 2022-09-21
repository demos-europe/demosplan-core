<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;

/**
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\EntityInterface
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
