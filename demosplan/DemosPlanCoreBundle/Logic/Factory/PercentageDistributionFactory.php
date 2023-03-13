<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Factory;


use DemosEurope\DemosplanAddon\Contracts\ValueObject\PercentageDistributionInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\PercentageDistribution;

class PercentageDistributionFactory implements PercentageDistributionFactoryInterface
{
    public function createPercentageDistribution(int $total, array $absolutes): PercentageDistributionInterface
    {
        return new PercentageDistribution($total, $absolutes);
    }
}
