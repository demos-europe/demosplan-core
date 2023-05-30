<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use LogicException;

class ValueObjectException extends LogicException
{
    public static function failedToParseAccessor($methodName): self
    {
        return new self("Failed to parse accessor, expected (get|set)Property, got {$methodName}");
    }

    public static function unknownAccessorPrefix($methodName): self
    {
        return new self("Unknown method: {$methodName}");
    }

    public static function mustLockFirst(): self
    {
        return new self('ValueObject is not locked! Please use $this->lock() first');
    }

    public static function unknownProperty(string $propertyName, string $context): self
    {
        return new self("Property {$propertyName} does not exist in {$context}");
    }

    public static function noAccessorAllowedFromTwig(): self
    {
        return new self('Please use the dot notation to access value object properties from twig');
    }

    public static function noChangeAllowedWhenLocked(): self
    {
        return new self('ValueObject is locked and cannot be changed');
    }

    public static function mustProvideArgument(array $arguments): self
    {
        $argumentCount = count($arguments);

        return new self("Setting properties requires exactly one value, got {$argumentCount}");
    }
}
