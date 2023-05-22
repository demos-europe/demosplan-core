<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Factory\PercentageDistributionFactoryInterface;
use DemosEurope\DemosplanAddon\Contracts\ValueObject\PercentageDistributionInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\PercentageDistribution;

class PercentageDistributionFactory implements PercentageDistributionFactoryInterface
{
    public function createPercentageDistribution(int $total, array $absolutes): PercentageDistributionInterface
    {
        return new PercentageDistribution($total, $absolutes);
    }
}
