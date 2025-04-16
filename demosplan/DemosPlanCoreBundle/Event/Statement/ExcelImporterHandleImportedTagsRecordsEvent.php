<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterHandleImportedTagsRecordsEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use League\Csv\MapIterator;

class ExcelImporterHandleImportedTagsRecordsEvent extends DPlanEvent implements ExcelImporterHandleImportedTagsRecordsEventInterface
{
    public function __construct(private $records, private array $columnTitles, private $tags = [])
    {
    }

    public function getColumnTitles(): array
    {
        return $this->columnTitles;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getRecords(): MapIterator
    {
        return new MapIterator($this->records, fn($record) => $record);
    }
}
