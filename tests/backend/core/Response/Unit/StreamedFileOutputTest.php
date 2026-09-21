<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Response\Unit;

use demosplan\DemosPlanCoreBundle\Response\StreamedFileOutput;
use Tests\Base\UnitTestCase;

class StreamedFileOutputTest extends UnitTestCase
{
    public function testSendWritesTheCompleteStream(): void
    {
        // Arrange - more than one chunk, so a partial write would show up
        $content = str_repeat('demosplan', 250_000);
        $stream = $this->streamContaining($content);

        // Act
        $sent = $this->capture($stream);

        // Assert
        self::assertSame(strlen($content), strlen($sent));
        self::assertSame(md5($content), md5($sent));
    }

    public function testSendClosesTheStream(): void
    {
        // Arrange
        $stream = $this->streamContaining('zip-bytes');

        // Act
        $this->capture($stream);

        // Assert
        self::assertFalse(is_resource($stream));
    }

    public function testSendKeepsTheOutputBufferLevelUntouched(): void
    {
        // Arrange - the buffer is flushed per chunk, but never closed
        $level = ob_get_level();

        // Act
        $this->capture($this->streamContaining('zip-bytes'));

        // Assert
        self::assertSame($level, ob_get_level());
    }

    /**
     * @return resource
     */
    private function streamContaining(string $content)
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * Collects via an output handler, because the flushed chunks leave the buffer as they are written.
     *
     * @param resource $stream
     */
    private function capture($stream): string
    {
        $captured = '';
        ob_start(static function (string $chunk) use (&$captured): string {
            $captured .= $chunk;

            return '';
        });
        StreamedFileOutput::sendAndClose($stream);
        ob_end_clean();

        return $captured;
    }
}
