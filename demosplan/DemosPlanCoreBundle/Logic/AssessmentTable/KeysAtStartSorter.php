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

use function array_key_exists;

/**
 * Class KeysAtStartSorter
 * <p>
 * Sorts specific items in the given array at the beginning of the returned array.
 * The items are identified by the keys given on instantiation. The order of the keys given matter.
 */
class KeysAtStartSorter implements ArraySorterInterface
{
    /**
     * @var string[]|int[]
     */
    protected $keys;

    /**
     * @param string[]|int[] $keys
     *
     * @throws TypeError                thrown if $array is null or not an array
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
        $toBePlacedFirst = [];
        foreach ($this->keys as $key) {
            if (array_key_exists($key, $array)) {
                $toBePlacedFirst[$key] = $array[$key];
            }
        }

        // the + operator is intended as array_merge would reassigns numeric keys
        return $toBePlacedFirst + $array;
    }
}
