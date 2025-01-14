<?php

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
        return $this->records;
    }
}
