<?php

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterPrePersistSegmentsEventInterface;

class ExcelImporterPrePersistSegmentsEvent implements ExcelImporterPrePersistSegmentsEventInterface
{
    protected array $segments;
    public function __construct(array $segments)
    {
        $this->segments = $segments;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }
}
