<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use TypeError;

/**
 * Class KeysAtEndSorter
 * <p>
 * Sorts specific items in the given array at the end of the returned array.
 * The items are identified by the keys given on instantiation. The order of the keys given matter.
 */
class KeysAtEndSorter implements ArraySorterInterface
{
    /**
     * @var string[]|int[]
     */
    protected $keys;

    /**
     * @param string[]|int[] $keys
     *
     * @throws TypeError                if $keys is null or not an array
     * @throws InvalidArgumentException thrown if $keys is empty
     */
    public function __construct(array $keys)
    {
        if (0 === count($keys)) {
            throw new InvalidArgumentException('given array of keys is empty');
        }
        $this->keys = $keys;
    }

    public function sortArray(array $array): array
    {
        if (0 === count($array)) {
            return $array;
        }
        // Sort array keys based on their position in $this->keys
        uksort($array, function ($a, $b) {
            $posA = array_search($a, $this->keys);
            $posB = array_search($b, $this->keys);

            // If both keys are in $this->keys, compare their positions
            // If only one key is in $this->keys, prioritize moving it to the end
            return ($posA && $posB) ? $posA - $posB : ($posB ? -1 : 0);
        });

        return $array;
    }
}
