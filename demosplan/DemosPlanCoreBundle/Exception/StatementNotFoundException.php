<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class StatementNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $id): StatementNotFoundException
    {
        return new self("Statement with ID {$id} was not found.");
    }

    public static function createFromNonOriginalId(string $id): StatementNotFoundException
    {
        return new self("The original statement with the ID {$id} was not found.");
    }
}
