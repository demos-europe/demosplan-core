<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\HistoryDay;
use League\Fractal\Resource\Collection;

class HistoryDayTransformer extends BaseTransformer
{
    protected $type = 'HistoryDay';

    protected array $defaultIncludes = [
        'historyTimes',
    ];

    public function transform(HistoryDay $historyDay)
    {
        return [
            'id'  => 'thisNeverGetsUsed_'.$historyDay->getDay(),
            'day' => $historyDay->getDay(),
        ];
    }

    public function includeHistoryTimes(HistoryDay $historyDay): Collection
    {
        return $this->resourceService->makeCollection($historyDay->getTimes(), HistoryTimeTransformer::class);
    }
}
