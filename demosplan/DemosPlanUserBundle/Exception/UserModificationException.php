<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Exception;

use LogicException;

class UserModificationException extends LogicException
{
    /**
     * @param mixed $roles
     *
     * @return UserModificationException
     */
    public static function rolesMustBeArray($roles): self
    {
        $rolesType = gettype($roles);

        return new self("`roles` mus be an array, got `{$rolesType}`");
    }
}
