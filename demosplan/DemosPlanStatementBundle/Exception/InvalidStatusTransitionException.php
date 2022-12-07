<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Exception;

use Exception;

class InvalidStatusTransitionException extends Exception
{
    /**
     * @return InvalidStatusTransitionException
     */
    public static function create(string $currentStatus, string $newStatus): self
    {
        return new self("Invalid status transition $currentStatus => $newStatus");
    }
}
