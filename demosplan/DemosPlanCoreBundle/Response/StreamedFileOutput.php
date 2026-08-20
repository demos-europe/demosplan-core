<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Response;

/**
 * Sends a file stream to the client without holding it in memory.
 *
 * fpassthru() hands the complete file to the output layer in a single write, which an active output
 * buffer has to grow to hold - a download of more than a gigabyte exhausts the memory limit that
 * way. Writing in chunks and flushing each one keeps the memory use flat regardless of file size.
 */
final class StreamedFileOutput
{
    private const CHUNK_SIZE = 1024 * 1024;

    /**
     * @param resource $stream closed when the file has been sent
     */
    public static function sendAndClose($stream): void
    {
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK_SIZE);
                if (false === $chunk) {
                    break;
                }
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        } finally {
            fclose($stream);
        }
    }
}
