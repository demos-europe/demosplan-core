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
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterItem;

class AggregationFilterItemTransformer extends BaseTransformer
{
    protected $type = 'AggregationFilterItem';

    public function transform(AggregationFilterItem $aggregationFilterItem): array
    {
        return [
            'id'          => $aggregationFilterItem->getId(),
            'label'       => $aggregationFilterItem->getLabel(),
            'description' => $aggregationFilterItem->getDescription(),
            'count'       => $aggregationFilterItem->getCount(),
            'selected'    => $aggregationFilterItem->getSelected(),
        ];
    }
}
