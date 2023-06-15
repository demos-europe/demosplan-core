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

use Psr\Log\LoggerInterface;

class ArrayHelper
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    // @improve T15851
    /**
     * If the given $dataArray has a key composed of given $keyPrefix and given $key,
     * the value of this key will put into the given $objectiveArray to the $key.
     *
     * @param array  $objectiveArray array in which the data will be stored
     * @param array  $dataArray      array, which contains the data, which will be store in the given $objectiveArray
     * @param string $key            string used as key for access a specific data in the given $objectiveArray
     * @param string $keyPrefix      string used as prefix for the $key for access a specific data in the given $dataArray
     *
     * @return array updated $objectiveArray
     */
    public function addToArrayIfKeyExists($objectiveArray, $dataArray, $key, $keyPrefix = 'r_'): array
    {
        if (array_key_exists($keyPrefix.$key, $dataArray)) {
            $objectiveArray[$key] = $dataArray[$keyPrefix.$key];
        }

        return $objectiveArray;
    }

    /**
     * Sortiere einen Array an Hand von ids in der manualOrder
     * Die Lösung ist unelegant, bei eleganteren hat php gestreikt. To be improved.
     *
     * @param array<int, string> $ids
     * @param string             $orderByKey
     */
    public function orderArrayByIds(array $ids, array $arrayToOrder, $orderByKey = 'ident'): array
    {
        $orderedResult = [];
        $processedEntries = [];
        foreach ($ids as $moItemId) {
            // do not sort identical Items twice
            if (in_array($moItemId, $processedEntries)) {
                continue;
            }
            foreach ($arrayToOrder as $item) {
                if (!isset($item[$orderByKey])) {
                    $this->logger->warning(
                        'List could not be manually ordered because array has no key '.$orderByKey.' '.print_r(
                            $item,
                            true
                        )
                    );

                    return $arrayToOrder;
                }
                if ($item[$orderByKey] == $moItemId) {
                    $orderedResult[] = $item;
                    $processedEntries[] = $moItemId;
                }
            }
        }

        // append items not listed in manual sort order
        if (count($arrayToOrder) !== count($orderedResult)) {
            foreach ($arrayToOrder as $item) {
                if (!isset($item[$orderByKey])) {
                    $this->logger->warning(
                        'List could not be manually ordered because array has no key '.$orderByKey.' '.print_r(
                            $item,
                            true
                        )
                    );

                    return $arrayToOrder;
                }
                // append to result
                if (!in_array($item[$orderByKey], $processedEntries)) {
                    $orderedResult[] = $item;
                }
            }
        }

        return $orderedResult;
    }
}
