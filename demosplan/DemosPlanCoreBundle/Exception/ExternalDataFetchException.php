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
use Throwable;

class ExternalDataFetchException extends RuntimeException
{
    private function __construct(?string $message, ?int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function fetchFailed(int $status, Throwable $previous = null): self
    {
        return new self("Fetch failed with response status {$status}", $status, $previous);
    }
}
