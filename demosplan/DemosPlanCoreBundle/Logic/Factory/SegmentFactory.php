<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Factory\SegmentFactoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

class SegmentFactory implements SegmentFactoryInterface
{
    public function createNew(): SegmentInterface
    {
        return new Segment();
    }
}
