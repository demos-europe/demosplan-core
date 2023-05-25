<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class ProcedureNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $id): ProcedureNotFoundException
    {
        return new self("Procedure with the ID {$id} was not found.");
    }
}
