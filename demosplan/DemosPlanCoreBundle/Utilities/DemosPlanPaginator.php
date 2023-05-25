<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities;

use Pagerfanta\Pagerfanta;

/**
 * This class enhances Pagerfanta Object with custom features like pagination limits
 * Class DemosPlanPaginator.
 */
class DemosPlanPaginator extends Pagerfanta
{
    /** @var array */
    protected $limits = [25, 50, 100];

    /**
     * Is the result a subset of all results due to a filter or search?
     * null is used for BC to avoid confusion. If false would be default existing
     * cases would be hard to understand as filtered results may be returned but
     * this property will be false anyway.
     *
     * @var bool|null
     */
    protected $filtered;

    /**
     * @return array
     */
    public function getLimits()
    {
        return $this->limits;
    }

    /**
     * @param array $limits
     */
    public function setLimits($limits)
    {
        $this->limits = $limits;
    }

    /**
     * @return bool|null
     */
    public function isFiltered()
    {
        return $this->filtered;
    }

    /**
     * @param bool|null $filtered
     */
    public function setFiltered($filtered)
    {
        $this->filtered = $filtered;
    }
}
