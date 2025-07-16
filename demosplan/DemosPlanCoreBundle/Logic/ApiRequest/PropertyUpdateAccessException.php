<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use DemosEurope\DemosplanAddon\Contracts\Exceptions\PropertyUpdateAccessExceptionInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\AccessException;

class PropertyUpdateAccessException extends AccessException implements PropertyUpdateAccessExceptionInterface
{
    public static function notAvailable(ResourceTypeInterface $type, string $property, string ...$availableProperties): self
    {
        $propertyList = implode(',', $availableProperties);

        return new self($type, "No property '$property' is available for the type '{$type->getTypeName()}'. Available properties are: $propertyList");
    }

    public static function intPropertyKey(ResourceTypeInterface $type, int $propertyName): self
    {
        return new self($type, "Property name must be a string, not an integer. Received '$propertyName'.");
    }
}
