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
 * Thrown when the body of an export response cannot be written to a temporary file.
 */
class ExportResponseCaptureException extends RuntimeException
{
    public static function temporaryFileNotCreated(): self
    {
        return new self('Could not create temporary file for export');
    }

    public static function temporaryFileNotOpened(string $path): self
    {
        return new self("Could not open temporary file for export: {$path}");
    }
}
