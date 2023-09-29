<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class InvalidPostDataException extends Exception
{
    public static function createForMissingParameter(string $parameterKey): MissingPostParameterException
    {
        return new MissingPostParameterException("The request did not contain the parameter {$parameterKey} at all.");
    }

    public static function createForInvalidParameterType(string $parameterKey, string $expectedType): InvalidPostParameterTypeException
    {
        return new InvalidPostParameterTypeException("The value received for the key {$parameterKey} did not have the type {$expectedType}.");
    }
}
