<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * With the normal {@link PropertyAccessor} null values are only possible as the last value. Eg.
 * accessing the organisation name of user using <code>orga.name</code> with the organisation being
 * <code>null</code> will throw an exception:.
 *
 * <code>"PropertyAccessor requires a graph of objects or arrays to operate on, but it found type "NULL" while trying to traverse path "orga.name" at property "name"."</code>
 *
 * This class will return <code>null</code> instead for those cases.
 */
class NullablePropertyAccessor extends PropertyAccessor
{
    /**
     * @param array|object                 $objectOrArray
     * @param string|PropertyPathInterface $propertyPath
     *
     * @return mixed|null
     */
    public function getValue($objectOrArray, $propertyPath): mixed
    {
        try {
            return parent::getValue($objectOrArray, $propertyPath);
        } catch (UnexpectedTypeException) {
            return null;
        }
    }
}
