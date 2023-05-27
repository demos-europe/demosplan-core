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
 * @method string                   getId()
 * @method string                   getLabel()
 * @method string                   getPath()
 * @method AggregationFilterGroup[] getAggregationFilterGroups()
 * @method int                      getMissingResourcesSum()
 * @method AggregationFilterItem[]  getAggregationFilterItems()
 */
class AggregationFilterType extends ValueObject
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
     * @var string
     */
    protected $path;

    /**
     * @var array<int,AggregationFilterGroup>
     */
    protected $aggregationFilterGroups;

    /**
     * The items that are not placed in any group. They reside on the same level as the groups instead.
     *
     * @var array<int,AggregationFilterItem>
     */
    protected $aggregationFilterItems;

    /**
     * The count of all resources that are not part of any of the
     * {@link AggregationFilterItem::getCount() items} in this instance.
     *
     * Or in other words the count of the resources that don't have any values assigned in the
     * field that corresponds to this instance.
     *
     * Eg. if {@link Segment}s are to be filtered and this {@link AggregationFilterType} instance
     * corresponds to `tags` of the {@link Segment}s then this integer will be the count of all
     * {@link Segment}s that do not have any tags assigned.
     *
     * @var int
     */
    protected $missingResourcesSum;

    /**
     * @var bool
     */
    private $itemToManyRelationship;

    /**
     * @var bool
     */
    private $missingResourcesSumVisible;

    /**
     * @param array<string,AggregationFilterItem> $aggregationFilterItems
     * @param array<int,AggregationFilterGroup>   $aggregationFilterGroups
     */
    public function __construct(
        string $id,
        string $label,
        string $path,
        array $aggregationFilterItems,
        array $aggregationFilterGroups,
        int $missingResourcesSum,
        bool $itemToManyRelationship,
        bool $missingResourcesSumVisible
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->path = $path;
        $this->aggregationFilterItems = $aggregationFilterItems;
        $this->aggregationFilterGroups = $aggregationFilterGroups;
        $this->missingResourcesSum = $missingResourcesSum;
        $this->itemToManyRelationship = $itemToManyRelationship;
        $this->missingResourcesSumVisible = $missingResourcesSumVisible;
        $this->lock();
    }

    public function isItemToManyRelationship(): bool
    {
        return $this->itemToManyRelationship;
    }

    public function isMissingResourcesSumVisible(): bool
    {
        return $this->missingResourcesSumVisible;
    }
}
