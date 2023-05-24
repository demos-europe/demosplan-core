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

class BadRequestException extends LogicException
{
    public static function normalizerFailed()
    {
        return new self('Incoming JSON could not be converted.');
    }

    public static function idMismatch(): BadRequestException
    {
        return new self('The ID provided in the URL and the ID provided for the resource in the request body are not the same.');
    }

    public static function unknownQueryHash(string $queryHash): BadRequestException
    {
        return new self("No stored query was found for the given filter hash: $queryHash");
    }
}
