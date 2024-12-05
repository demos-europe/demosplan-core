<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use DemosEurope\DemosplanAddon\Contracts\SegmentExcelImportDataGetterInterface;


class SegmentExcelImportDataGetter implements SegmentExcelImportDataGetterInterface
{
    public function getSegmentData(array $columnNamesSegments, array $segmentData): array
    {
        return \array_combine($columnNamesSegments, $segmentData);
    }
}
