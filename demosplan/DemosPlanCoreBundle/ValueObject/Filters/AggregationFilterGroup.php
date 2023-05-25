<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Filters;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string                  getId()
 * @method string                  getLabel()
 * @method AggregationFilterItem[] getAggregationFilterItems()
 */
class AggregationFilterGroup extends ValueObject
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var AggregationFilterItem[]
     */
    protected $aggregationFilterItems;

    /**
     * @param AggregationFilterItem[] $aggregationFilterItems
     */
    public function __construct(string $id, string $label, array $aggregationFilterItems)
    {
        $this->id = $id;
        $this->label = $label;
        $this->aggregationFilterItems = $aggregationFilterItems;
        $this->lock();
    }
}
