<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanAssessmentTableBundle\Logic;

use function array_key_exists;

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
        foreach ($this->keys as $key) {
            if (array_key_exists($key, $array)) {
                $toBePlacedLast = $array[$key];
                unset($array[$key]);
                $array[$key] = $toBePlacedLast;
            }
        }

        return $array;
    }
}
