<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;

/**
 * @method array                   getFilterSet()
 * @method void                    setFilterSet(array $filterSet)
 * @method array                   getSortingSet()
 * @method void                    setSortingSet(array $sortingSet)
 * @method array                   getResult()
 * @method void                    setResult(array $result)
 * @method DemosPlanPaginator|null getPager()
 * @method void                    setPager(DemosPlanPaginator|null $demosPlanPaginator)
 * @method array                   getSearchFields()
 * @method void                    setSearchFields(array $searchFields)
 * @method int                     getTotal()
 * @method void                    setTotal(int $total)
 * @method string                  getSearch()
 * @method void                    setSearch(int $search)
 */
class ElasticsearchResultSet extends ValueObject
{
    /**
     * @var array
     */
    protected $filterSet = [];
    /**
     * @var array<string, mixed>
     */
    protected $result = [];

    /**
     * @var array|null
     */
    protected $sortingSet = [];

    /**
     * @var array
     */
    protected $searchFields = [];

    /**
     * @var int
     */
    protected $total = 0;

    /**
     * @var string
     */
    protected $search = '';

    /**
     * @var DemosPlanPaginator|null
     */
    protected $pager;
}
