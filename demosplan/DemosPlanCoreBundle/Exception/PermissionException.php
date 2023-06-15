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

class PermissionException extends LogicException
{
    public static function invalidPermissionCheckOperator(string $operator): self
    {
        return new self("Invalid permission check operator: {$operator}");
    }
}
