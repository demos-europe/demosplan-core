<?php

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterPrePersistTagsEventInterface;

class ExcelImporterPrePersistTagsEvent implements ExcelImporterPrePersistTagsEventInterface
{
    protected array $segments;
    protected array $tags;
    public function __construct(array $segments = [], array $tags = [])
    {
        $this->segments = $segments;
        $this->tags = $tags;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
