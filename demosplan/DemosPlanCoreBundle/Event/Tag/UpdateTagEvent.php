<?php

namespace demosplan\DemosPlanCoreBundle\Event\Tag;

use DemosEurope\DemosplanAddon\Contracts\Events\UpdateTagEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class UpdateTagEvent extends DPlanEvent implements UpdateTagEventInterface
{
    public function __construct(private string $tagId)
    {
    }

    public function getTagId(): string
    {
        return $this->tagId;
    }
}
