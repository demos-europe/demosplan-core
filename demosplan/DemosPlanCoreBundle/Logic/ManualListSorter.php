<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use Exception;

class ManualListSorter
{
    public function __construct(private readonly ArrayHelper $arrayHelper, private readonly ManualListSortRepository $manualListSortRepository)
    {
    }

    /**
     * Order array by manually set List order, if set.
     *
     * @return array{list: array, sorted: bool}
     */
    public function orderByManualListSort(
        string $manualSortScope,
        string $procedureId,
        string $namespace,
        array $result,
        string $orderByKey = 'ident',
        ?CustomerInterface $customer = null,
    ): array {
        $orderedResult = [
            'sorted' => false,
            'list'   => $result,
        ];

        // fetch the sort order from DB
        $manualSort = $this->manualListSortRepository->findOneBy(
            ['context' => $manualSortScope, 'pId' => $procedureId, 'namespace' => $namespace, 'customer' => $customer]
        );

        if (null !== $manualSort) {
            $manualOrder = explode(',', (string) $manualSort->getIdents());
            $orderedResult['list'] = $this->arrayHelper->orderArrayByIds($manualOrder, $result, $orderByKey);
            $orderedResult['sorted'] = true;
        }

        return $orderedResult;
    }

    /**
     * @param string $context
     *
     * @throws Exception
     */
    public function setManualSort($context, array $data): bool
    {
        return $this->manualListSortRepository->setManualSort($context, $data);
    }
}
