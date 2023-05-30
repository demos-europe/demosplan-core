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
 * @method array                   getAggregations()
 * @method void                    setAggregations(array $aggregations)
 * @method array                   getHits()
 * @method void                    setHits(array $hits)
 * @method DemosPlanPaginator|null getPager()
 * @method void                    setPager(DemosPlanPaginator|null $demosPlanPaginator)
 * @method array                   getSearchFields()
 * @method void                    setSearchFields(array $searchFields)
 * @method string|null             getUserWarning()
 * @method void                    setUserWarning(string|null $userWarning)
 */
class ElasticsearchResult extends ValueObject
{
    /**
     * @var array
     */
    protected $aggregations;
    /**
     * @var array{hits: array<string, mixed>, total: int}
     */
    protected $hits;

    /**
     * @var DemosPlanPaginator|null
     */
    protected $pager;

    /**
     * @var array
     */
    protected $searchFields;

    /**
     * @var string|null
     */
    protected $userWarning;
}
