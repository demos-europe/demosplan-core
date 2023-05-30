<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class RoleNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $roleId): RoleNotFoundException
    {
        return new self("Role with ID {$roleId} was not found.");
    }
}
