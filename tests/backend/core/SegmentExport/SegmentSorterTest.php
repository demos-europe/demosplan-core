<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\SegmentExport;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\SegmentSorter;
use Tests\Base\FunctionalTestCase;

class SegmentSorterTest extends FunctionalTestCase
{
    /** @var SegmentSorter */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentSorter::class);
    }

    public function testSortSegmentsByOrderInProcedure(): void
    {
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne()->_real();
        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne()->_real();
        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne()->_real();
        /** @var SegmentInterface $segment4 */
        $segment4 = SegmentFactory::createOne()->_real();
        $segment1->setOrderInProcedure(1);
        $segment2->setOrderInProcedure(2);
        $segment3->setOrderInProcedure(3);
        $segment4->setOrderInProcedure(4);
        $segments = [
            1 => $segment2,
            3 => $segment4,
            0 => $segment1,
            2 => $segment3,
        ];

        $sortedSegments = $this->sut->sortSegmentsByOrderInProcedure($segments);

        static::assertSame($segment1, $sortedSegments[0]);
        static::assertSame($segment2, $sortedSegments[1]);
        static::assertSame($segment3, $sortedSegments[2]);
        static::assertSame($segment4, $sortedSegments[3]);
    }
}
