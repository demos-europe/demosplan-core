<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\SegmentExportInfo;
use PHPUnit\Framework\TestCase;

class SegmentExportInfoTest extends TestCase
{
    public function testIsNotFilteredWithoutAnyFilter(): void
    {
        $sut = new SegmentExportInfo(null, null, null, null, null, null, false);

        self::assertFalse($sut->getIsFiltered());
    }

    public function testIsFilteredWithACustomFieldFilter(): void
    {
        $sut = new SegmentExportInfo(null, null, null, null, null, null, false, true);

        self::assertTrue($sut->getIsFiltered());
        self::assertTrue($sut->getHasCustomFieldFilter());
    }
}
