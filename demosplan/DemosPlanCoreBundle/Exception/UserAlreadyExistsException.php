<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

class UserAlreadyExistsException extends RuntimeException
{
    protected $value;

    public function getValue()
    {
        return $this->value;
    }

    public function setValue(mixed $value)
    {
        $this->value = $value;
    }
}
