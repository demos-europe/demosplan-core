<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\FacetInterface;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use Elastica\Index;

/**
 * @template T of object
 *
 * @template-extends ResourceTypeInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, T>
 */
interface ReadableEsResourceTypeInterface extends ResourceTypeInterface
{
    public function getQuery(): AbstractQuery;

    /**
     * @return array<int,string>
     */
    public function getScopes(): array;

    public function getSearchType(): Index;

    /**
     * Returns the mapping from the key identifying the aggregation as it is set in the
     * elasticsearch.yml to the corresponding facet definition.
     *
     * @return array<string,FacetInterface<object>>
     */
    public function getFacetDefinitions(): array;
}
