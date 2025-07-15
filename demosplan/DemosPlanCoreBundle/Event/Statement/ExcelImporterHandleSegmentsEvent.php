<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterHandleSegmentsEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class ExcelImporterHandleSegmentsEvent extends DPlanEvent implements ExcelImporterHandleSegmentsEventInterface
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

    public function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }
}
