<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use Carbon\Carbon;

abstract class ProcedureTimestampSorter implements ProcedureSorterInterface
{
    /**
     * Uses a callback to use the same sorting implementation on both legacy array procedures as well as entity
     * procedures.
     *
     * @param array    $procedures           the procedures to sort
     * @param callable $getTimestampCallback must take a procedure and must return a timestamp
     *
     * @return array a sorted array of procedures with each procedure with the same type as the one in the given
     *               procedures array
     */
    protected function sortArbitrary(array $procedures, callable $getTimestampCallback): array
    {
        $currTimestamp = Carbon::now()->startOfDay()->timestamp;
        // split procedures into two groups, the first containing such with dates in the future and
        // the second containing dates in the past

        // precalculate sortValue once that is used later to sort procedures
        $procedureCollection = collect($procedures)->transform(static function ($procedure) use ($getTimestampCallback) {
            $procedure['sortValue'] = $getTimestampCallback($procedure);

            return $procedure;
        });

        // split collection into groups
        $groups = $procedureCollection->groupBy(
            static function ($procedure/* , $key */) use ($currTimestamp) {
                return $currTimestamp <= $procedure['sortValue'] ? 0 : 1;
            }
        );

        // sort the first group (future) ascending and the second group (past) descending and return them
        // both concatenated
        $firstGroupCollect = \collect($groups->get(0, []));
        $firstGroupSorted = $firstGroupCollect->sortBy('sortValue');
        $secondGroupCollect = \collect($groups->get(1, []));
        $secondGroupSorted = $secondGroupCollect->sortByDesc('sortValue');

        return $firstGroupSorted->merge($secondGroupSorted)->all();
    }
}
