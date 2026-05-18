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

use Exception;

/**
 * Exception thrown when ODT file processing fails.
 */
class OdtProcessingException extends Exception
{
    public static function unableToOpenFile(string $filePath): self
    {
        return new self("Unable to open ODT file: {$filePath}");
    }

    public static function extractionFailed(string $filePath, string $reason = ''): self
    {
        $message = "ODT extraction failed for file: {$filePath}";
        if ('' !== $reason && '0' !== $reason) {
            $message .= " Reason: {$reason}";
        }

        return new self($message);
    }

    public static function processingFailed(string $reason): self
    {
        return new self("ODT processing failed: {$reason}");
    }
}
