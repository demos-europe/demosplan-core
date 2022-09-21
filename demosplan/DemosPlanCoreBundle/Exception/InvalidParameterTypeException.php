<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class InvalidParameterTypeException extends InvalidArgumentException
{
    /**
     * @param class-string             $actualType
     * @param array<int, class-string> $allowedTypes
     */
    public static function fromTypes(string $actualType, array $allowedTypes): self
    {
        $allowedTypes = array_map(static function (string $allowedType): string {
            return "'$allowedType'";
        }, $allowedTypes);
        $allowedTypesString = implode(', ', $allowedTypes);

        return new self("Invalid parameter type '$actualType' given, expected one of the following: $allowedTypesString.");
    }
}
