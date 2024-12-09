<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use DemosEurope\DemosplanAddon\Contracts\SegmentExcelImportDataGetterInterface;

class SegmentExcelImportDataGetter implements SegmentExcelImportDataGetterInterface
{
    public function getSegmentData(array $columnNamesSegments, array $segmentData): array
    {
        return \array_combine($columnNamesSegments, $segmentData);
    }
}
