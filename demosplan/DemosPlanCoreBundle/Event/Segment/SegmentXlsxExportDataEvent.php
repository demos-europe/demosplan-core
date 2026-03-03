<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Segment;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentXlsxExportDataEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

/**
 * Dispatched once per segment during the XLSX export data build.
 * Subscribers may enrich the export data array, e.g. by adding topicalTagNames
 * and nonTopicalTagNames keys.
 * Without any subscriber the export data remains unchanged.
 */
class SegmentXlsxExportDataEvent extends DPlanEvent implements SegmentXlsxExportDataEventInterface
{
    public function __construct(
        private readonly SegmentInterface $segment,
        private array $exportData,
    ) {
    }

    public function getSegment(): SegmentInterface
    {
        return $this->segment;
    }

    public function getExportData(): array
    {
        return $this->exportData;
    }

    public function setExportData(array $exportData): void
    {
        $this->exportData = $exportData;
    }
}
