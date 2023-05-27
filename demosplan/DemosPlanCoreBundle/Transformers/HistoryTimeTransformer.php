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

    /**
     * @var EntityContentChangeService
     */
    private $entityContentChangeService;

    public function __construct(EntityContentChangeService $entityContentChangeService)
    {
        parent::__construct();
        $this->entityContentChangeService = $entityContentChangeService;
    }

    public function transform(HistoryTime $historyTime): array
    {
        $entityType = $historyTime->getEntityType();
        $fieldNames = array_map(function (string $fieldName) use ($entityType): string {
            return $this->entityContentChangeService->getMappingValue(
                $fieldName,
                $entityType,
                'translationKey'
            );
        }, $historyTime->getFieldNames());

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
