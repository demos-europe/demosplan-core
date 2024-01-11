<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiListResultInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterType;
use Pagerfanta\Pagerfanta;

/**
 * @template O of UuidEntityInterface
 *
 * @method array|null getFacets()
 * @method int        getResultCount()
 */
class ApiListResult extends ValueObject implements ApiListResultInterface
{
    /**
     * array<int,object>.
     */
    protected $list;

    /**
     * @var array<string,mixed>
     */
    protected $meta;

    /**
     * Null if the facets are unknown.
     *
     * @var array<string,AggregationFilterType>|null
     */
    protected $facets;

    /**
     * @var int
     */
    protected $resultCount;

    /**
     * @var Pagerfanta|null
     */
    protected $paginator;

    /**
     * @param array<int,O>                             $filteredList
     * @param array<string,mixed>                      $meta
     * @param array<string,AggregationFilterType>|null $facets       if `null` then the size of the filtered list is used
     */
    public function __construct(array $filteredList, array $meta, ?array $facets, int $resultCount = null, Pagerfanta $paginator = null)
    {
        $this->list = $filteredList;
        $this->meta = $meta;
        $this->facets = $facets;
        $this->resultCount = $resultCount ?? count($filteredList);
        $this->paginator = $paginator;
        $this->lock();
    }

    /**
     * @return array<int, object>
     */
    public function getList(): array
    {
        return $this->list;
    }

    public function getPaginator(): ?Pagerfanta
    {
        return $this->paginator;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }
}
