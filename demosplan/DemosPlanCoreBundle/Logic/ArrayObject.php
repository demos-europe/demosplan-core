<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use ArrayIterator;

class ArrayObject extends \ArrayObject
{
    public function __construct($input = [], $flags = 0, $iterator_class = ArrayIterator::class)
    {
        // set Values in custom array property and in \ArrayObject store
        $defaultClassVars = get_class_vars(static::class);
        $mergedValues = array_merge($defaultClassVars, $input);
        parent::__construct($mergedValues, $flags, $iterator_class);
    }

    public function offsetExists($offset): bool
    {
        if (0 == parent::count()) {
            return false;
        }

        return array_key_exists($offset, parent::getArrayCopy()) ? true : property_exists($this, $offset);
    }

    public function offsetGet($offset): mixed
    {
        $getterMethod = 'get'.ucfirst((string) $offset);
        if (method_exists($this, $getterMethod)) {
            return $this->$getterMethod();
        }
        if (property_exists($this, $offset)) {
            return $this->$offset;
        }
        if (0 == parent::count()) {
            return false;
        }
        if (array_key_exists($offset, parent::getArrayCopy())) {
            return parent::offsetGet($offset);
        }

        return null;
    }

    public function offsetSet($offset, $value): void
    {
        parent::offsetSet($offset, $value);

        // update object
        if (!is_null($offset)) {
            // update object on array set access
            $setterMethod = 'set'.ucfirst((string) $offset);
            if (method_exists($this, $setterMethod)) {
                $this->$setterMethod($value);
            }
        }
    }

    /**
     * Return both public and nonPublic properties as count.
     *
     * There may be a slight difference if not all Properties have getters
     * or there are more getters than properties, but for the old checks
     * ```0 < count($arrayObject)``` to check whether array is populated this
     * should be sufficient
     */
    public function count(): int
    {
        $publicProperties = parent::count();
        $nonPublicProperties = count(get_class_vars(static::class));

        return $publicProperties + $nonPublicProperties;
    }
}
