<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Deployment;

class StrategyLoader
{
    /**
     * @var array
     */
    protected $strategies;

    /**
     * @param array<StrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = [];

        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->getName()] = $strategy;
        }
    }

    /**
     * @param string $name
     *
     * @return Strategy
     */
    public function get($name)
    {
        return $this->strategies[$name];
    }
}
