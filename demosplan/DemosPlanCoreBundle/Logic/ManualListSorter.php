<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use Exception;

class ManualListSorter
{
    /**
     * @var ArrayHelper
     */
    private $arrayHelper;
    /**
     * @var ManualListSortRepository
     */
    private $manualListSortRepository;

    public function __construct(ArrayHelper $arrayHelper, ManualListSortRepository $manualListSortRepository)
    {
        $this->arrayHelper = $arrayHelper;
        $this->manualListSortRepository = $manualListSortRepository;
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
        string $orderByKey = 'ident'
    ): array {
        $orderedResult = [
            'sorted' => false,
            'list'   => $result,
        ];

        //fetch the sort order from DB
        $manualSort = $this->manualListSortRepository->findOneBy(
            ['context' => $manualSortScope, 'pId' => $procedureId, 'namespace' => $namespace]
        );

        if (null !== $manualSort) {
            $manualOrder = explode(',', $manualSort->getIdents());
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
