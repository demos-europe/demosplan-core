<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Filters;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterGroup;
use League\Fractal\Resource\Collection;

class AggregationFilterGroupTransformer extends BaseTransformer
{
    protected $type = 'AggregationFilterGroup';

    protected array $defaultIncludes = [
        'aggregationFilterItems',
    ];

    public function transform(AggregationFilterGroup $aggregationFilterGroup): array
    {
        return [
            'id'    => $aggregationFilterGroup->getId(),
            'label' => $aggregationFilterGroup->getLabel(),
        ];
    }

    public function includeAggregationFilterItems(
        AggregationFilterGroup $aggregationFilterGroup
    ): Collection {
        return $this->resourceService->makeCollection(
            $aggregationFilterGroup->getAggregationFilterItems(),
            AggregationFilterItemTransformer::class
        );
    }
}
