<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DemosEurope\DemosplanAddon\Contracts\ValueObject\PercentageDistributionInterface;

/**
 * @method int   getTotal()
 * @method array getPercentages()
 * @method array getAbsolutes()
 */
class PercentageDistribution extends ValueObject implements PercentageDistributionInterface
{
    /** @var array<string,float> */
    protected $percentages;
    /** @var array<string,int> */
    protected $absolutes;
    /** @var int */
    protected $total;

    /**
     * @param array<string,int> $absolutes
     */
    public function __construct(int $total, array $absolutes)
    {
        $this->percentages = array_map(static function (int $absolute) use ($total) {
            return 0 !== $total
                ? round($absolute / $total * 100, 2)
                : 0;
        }, $absolutes);
        $this->absolutes = $absolutes;
        $this->total = $total;
        $this->lock();
    }
}
