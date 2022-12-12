<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use ArrayAccess;
use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ResourceObjectInterface;
use LogicException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use function array_key_exists;
use function data_get;

class ResourceObject implements ArrayAccess, ResourceObjectInterface
{
    /** @var array */
    protected $item;

    /**
     * @var TopLevel
     */
    protected $topLevel;

    public function __construct(array $item, TopLevel $topLevel)
    {
        $this->item = $item;
        $this->topLevel = $topLevel;
    }

    /**
     * This function assumes that a basic JSON API schema validation has been already done.
     *
     * Unhandled attribute names are all keywords of resource objects
     *
     * @param string $field
     *
     * @return mixed
     */
    public function get($field)
    {
        // if the field is a navigation expression in the item, return that as result
        if (null !== data_get($this->item, $field)) {
            return data_get($this->item, $field);
        }

        // if the field is found in the attributes just return the content for the field
        if (isset($this->item['attributes'][$field])) {
            return $this->item['attributes'][$field];
        }

        if (array_key_exists($field, $this->item['relationships'])) {
            // to-one relationship

            if (null === data_get($this->item, "relationships.{$field}.data")) {
                // empty to-one relationship
                return null;
            }

            $fieldType = data_get($this->item, "relationships.{$field}.data.type");

            if (null !== $fieldType) {
                // non-empty to-one relationship
                return $this->topLevel[$fieldType][data_get($this->item, "relationships.{$field}.data.id")];
            }

            // to-many relationship
            $items = [];

            // if the to-many relationship is empty, the `data_get` will result in not iterating over any items
            /** @var array $resourceIdentifierObject */
            foreach (data_get($this->item, "relationships.{$field}.data", []) as $resourceIdentifierObject) {
                $items[$resourceIdentifierObject['id']] = $this->topLevel[$resourceIdentifierObject['type']][$resourceIdentifierObject['id']];
            }

            return $items;
        }

        throw new InvalidArgumentException('no valid data found for field in resourceObject: '.$field);
    }

    /**
     * @param string $field
     */
    public function isPresent($field): bool
    {
        return null !== data_get($this->item, $field)
            || isset($this->item['attributes'][$field])
            || (
                array_key_exists('relationships', $this->item)
                && array_key_exists($field, $this->item['relationships']
                )
            );
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->item);
    }

    /**
     * @return array|mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new LogicException('readonly');
    }

    public function offsetUnset($offset): void
    {
        throw new LogicException('readonly');
    }
}
