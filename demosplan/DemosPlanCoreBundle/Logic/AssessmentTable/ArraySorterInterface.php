<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use TypeError;

/**
 * Interface ArraySorterInterface.
 *
 * @template TValue
 */
interface ArraySorterInterface
{
    /**
     * @template TKey
     *
     * @param array<TKey,TValue> $array The array to sort. Accepts empty arrays.
     *
     * @return array<TKey,TValue> the sorted array
     *
     * @throws TypeError thrown if $array is null or not an array
     */
    public function sortArray(array $array): array;
}
