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

use DemosEurope\DemosplanAddon\Contracts\Events\SegmentXlsxExportColumnsEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

/**
 * Dispatched once before the segment XLSX export is built.
 * Subscribers may replace or extend the column definitions array.
 * Without any subscriber the default tagNames/topicNames columns remain unchanged.
 */
class SegmentXlsxExportColumnsEvent extends DPlanEvent implements SegmentXlsxExportColumnsEventInterface
{
    public function __construct(private array $columnsDefinition)
    {
    }

    public function getColumnsDefinition(): array
    {
        return $this->columnsDefinition;
    }

    public function setColumnsDefinition(array $columnsDefinition): void
    {
        $this->columnsDefinition = $columnsDefinition;
    }
}
