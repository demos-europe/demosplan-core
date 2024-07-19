<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

class SegmentSorter
{
    public function sortSegmentsByOrderInProcedure(array $segments): array
    {
        uasort($segments, [$this, 'compareOrderInProcedure']);

        return $segments;
    }

    private function compareOrderInProcedure(Segment $segmentA, Segment $segmentB): int
    {
        return $segmentA->getOrderInProcedure() - $segmentB->getOrderInProcedure();
    }
}
