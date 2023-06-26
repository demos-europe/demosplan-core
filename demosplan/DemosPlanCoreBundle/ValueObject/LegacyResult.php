<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * @method array  getResult()
 * @method void   setResult(array $result)
 * @method array  getFilterSet()
 * @method void   setFilterSet(array $filterSet)
 * @method array  getSortingSet()
 * @method void   setSortingSet(array $sortingSet)
 * @method int    getTotal()
 * @method void   setTotal(int $total)
 * @method string getSearch()
 * @method void   setSearch(string $search)
 */
class LegacyResult extends ValueObject
{
    /**
     * @var array
     */
    protected $result;

    /**
     * @var array
     */
    protected $filterSet;

    /**
     * @var array
     */
    protected $sortingSet = [];

    /**
     * @var int
     */
    protected $total;

    /**
     * @var string
     */
    protected $search = '';

    public function __construct(array $result, array $filterSet, array $sortingSet, int $total, string $search)
    {
        $this->result = $result;
        $this->filterSet = $filterSet;
        $this->sortingSet = $sortingSet;
        $this->total = $total;
        $this->search = $search;
    }

    public function toArray(): array
    {
        return [
            'result'     => $this->result,
            'filterSet'  => $this->filterSet,
            'sortingSet' => $this->sortingSet,
            'total'      => $this->total,
            'search'     => $this->search,
        ];
    }
}
