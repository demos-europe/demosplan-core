<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Tag;

use DemosEurope\DemosplanAddon\Contracts\Events\TagListCsvExportEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class TagListCsvExportEvent extends DPlanEvent implements TagListCsvExportEventInterface
{
    public function __construct(
        private array $columnsDefinition,
        private array $rows,
    ) {
    }

    public function getColumnsDefinition(): array
    {
        return $this->columnsDefinition;
    }

    public function setColumnsDefinition(array $columnsDefinition): void
    {
        $this->columnsDefinition = $columnsDefinition;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }
}
