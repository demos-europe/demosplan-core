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
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterType;
use League\Fractal\Resource\Collection;

class AggregationFilterTypeTransformer extends BaseTransformer
{
    protected $type = 'AggregationFilterType';

    protected array $defaultIncludes = [
        'aggregationFilterGroups',
        'aggregationFilterItems',
    ];

    public function transform(AggregationFilterType $aggregationFilterType): array
    {
        return [
            'id'                      => $aggregationFilterType->getId(),
            'label'                   => $aggregationFilterType->getLabel(),
            'path'                    => $aggregationFilterType->getPath(),
            'missingResourcesSum'     => $aggregationFilterType->getMissingResourcesSum(),
            'showMissingResourcesSum' => $aggregationFilterType->isMissingResourcesSumVisible(),
            'itemToManyRelationship'  => $aggregationFilterType->isItemToManyRelationship(),
        ];
    }

    public function includeAggregationFilterGroups(
        AggregationFilterType $aggregationFilterType
    ): Collection {
        return $this->resourceService->makeCollection(
            $aggregationFilterType->getAggregationFilterGroups(),
            AggregationFilterGroupTransformer::class
        );
    }

    public function includeAggregationFilterItems(AggregationFilterType $aggregationFilterType): Collection
    {
        return $this->resourceService->makeCollection(
            $aggregationFilterType->getAggregationFilterItems(),
            AggregationFilterItemTransformer::class
        );
    }
}
