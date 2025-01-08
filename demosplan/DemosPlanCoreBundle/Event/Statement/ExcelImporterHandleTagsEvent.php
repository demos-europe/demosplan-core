<?php

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterHandleTagsEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class ExcelImporterHandleTagsEvent extends DPlanEvent implements ExcelImporterHandleTagsEventInterface
{
    protected array $tags;

    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}
