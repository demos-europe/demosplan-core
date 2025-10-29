<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

/**
 * Exception thrown when null bytes are detected in request input.
 * Null bytes can be used in various injection attacks and should cause request rejection.
 */
class NullByteDetectedException extends RuntimeException
{
    public function __construct(string $message = 'Null byte detected in input')
    {
        parent::__construct($message);
    }
}
