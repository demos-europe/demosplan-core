<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use PHPUnit\Framework\TestCase;
use ValueError;

class SegmentationStatusTest extends TestCase
{
    public function testUnsegmentedStatus(): void
    {
        $status = SegmentationStatus::UNSEGMENTED;

        self::assertEquals('unsegmented', $status->value);
        self::assertFalse($status->isSegmented());
    }

    public function testSegmentedStatus(): void
    {
        $status = SegmentationStatus::SEGMENTED;

        self::assertEquals('segmented', $status->value);
        self::assertTrue($status->isSegmented());
    }

    public function testFromString(): void
    {
        $status = SegmentationStatus::from('segmented');

        self::assertSame(SegmentationStatus::SEGMENTED, $status);
    }

    public function testFromInvalidStringThrowsException(): void
    {
        $this->expectException(ValueError::class);

        SegmentationStatus::from('invalid');
    }
}
