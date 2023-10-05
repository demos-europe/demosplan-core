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

class UserModificationException extends LogicException
{
    public static function rolesMustBeArray($roles): self
    {
        $rolesType = gettype($roles);

        return new self("`roles` mus be an array, got `{$rolesType}`");
    }
}
