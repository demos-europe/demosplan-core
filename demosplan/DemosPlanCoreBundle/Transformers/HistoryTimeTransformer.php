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
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\ValueObject\HistoryTime;

class HistoryTimeTransformer extends BaseTransformer
{
    protected $type = 'HistoryTime';

    public function __construct(private readonly EntityContentChangeService $entityContentChangeService)
    {
        parent::__construct();
    }

    public function transform(HistoryTime $historyTime): array
    {
        $entityType = $historyTime->getEntityType();
        $fieldNames = array_map(fn(string $fieldName): string => $this->entityContentChangeService->getMappingValue(
            $fieldName,
            $entityType,
            'translationKey'
        ), $historyTime->getFieldNames());

        return [
            'id'                                           => 'thisNeverGetsUsed_'.$historyTime->getCreated(),
            'anyEntityContentChangeIdOfThisChangeInstance' => $historyTime->getId(),
            'created'                                      => $historyTime->getCreated(),
            'displayChange'                                => $historyTime->getDisplayChange(),
            'userId'                                       => $historyTime->getUserId(),
            'userName'                                     => $historyTime->getUserName(),
            'fieldNames'                                   => $fieldNames,
        ];
    }
}
