<?php

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentDeleteEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class SegmentDeleteEvent extends DPlanEvent implements SegmentDeleteEventInterface
{
    protected SegmentInterface $segment;

    public function __construct(SegmentInterface $segment)
    {
        $this->segment = $segment;
    }

    public function getSegment(): SegmentInterface
    {
        return $this->segment;
    }
}
