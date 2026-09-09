<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Base\UnitTestCase;

class ExportResponseFileStoreTest extends UnitTestCase
{
    private const EXPORT_FAILURE_MESSAGE = 'export exploded';

    protected ?ExportResponseFileStore $sut = null;

    private ?FileService $fileServiceMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->sut = new ExportResponseFileStore($this->fileServiceMock);
    }

    public function testStoreCapturesTheCompleteResponseBody(): void
    {
        // Arrange - more than one capture chunk, with a partial chunk at the end
        $payload = str_repeat('x', (1024 * 256) + 1234);
        $response = new StreamedResponse(static function () use ($payload): void {
            echo $payload;
        });
        $capturedSize = null;
        $this->fileServiceMock->method('saveTemporaryFile')
            ->willReturnCallback(function (string $path) use (&$capturedSize): File {
                $capturedSize = filesize($path);

                return $this->fileWithHash('hash-1');
            });

        // Act
        $stored = $this->sut->store($response, 'u1', 'proc-1', 'fallback.zip');

        // Assert
        self::assertSame(strlen($payload), $capturedSize);
        self::assertSame('hash-1', $stored->getFileHash());
    }

    public function testStoreUsesFileNameFromContentDisposition(): void
    {
        // Arrange
        $response = new StreamedResponse(static function (): void {
            echo 'bytes';
        });
        $response->headers->set('Content-Disposition', "attachment; filename*=UTF-8''Abw%C3%A4gungstabelle.pdf");
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        $stored = $this->sut->store($response, 'u1', 'proc-1', 'fallback.zip');

        // Assert
        self::assertSame('Abwägungstabelle.pdf', $stored->getFileName());
    }

    public function testStoreFallsBackToGivenNameWithoutContentDisposition(): void
    {
        // Arrange - ZipStream sends its disposition through header(), which is a no-op in a worker
        $response = new StreamedResponse(static function (): void {
            echo 'bytes';
        });
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        $stored = $this->sut->store($response, 'u1', null, 'Verfahrensexport.zip');

        // Assert
        self::assertSame('Verfahrensexport.zip', $stored->getFileName());
    }

    public function testStoreLeavesNoOutputBufferBehindWhenSendingThrows(): void
    {
        // Arrange - for a streamed export the whole export runs inside sendContent(), so it may throw
        $response = new StreamedResponse(static function (): void {
            echo 'partial';
            throw new DemosException('error.export', self::EXPORT_FAILURE_MESSAGE);
        });
        $bufferLevelBefore = ob_get_level();

        // Act
        try {
            $this->sut->store($response, 'u1', 'proc-1', 'fallback.zip');
            self::fail('Expected the exception to propagate');
        } catch (DemosException $e) {
            self::assertSame(self::EXPORT_FAILURE_MESSAGE, $e->getMessage());
        }

        // Assert - a leaked buffer would swallow all later output of the worker process
        self::assertSame($bufferLevelBefore, ob_get_level());
    }

    public function testStoreRemovesTemporaryFileWhenSendingThrows(): void
    {
        // Arrange
        $response = new StreamedResponse(static function (): void {
            throw new DemosException('error.export', self::EXPORT_FAILURE_MESSAGE);
        });
        $temporaryFilesBefore = $this->countTemporaryExportFiles();

        // Act
        try {
            $this->sut->store($response, 'u1', 'proc-1', 'fallback.zip');
        } catch (DemosException) {
            // expected
        }

        // Assert - otherwise every failed export leaves a multi-hundred-MB file behind
        self::assertSame($temporaryFilesBefore, $this->countTemporaryExportFiles());
    }

    private function countTemporaryExportFiles(): int
    {
        return count(glob(sys_get_temp_dir().'/dplan_export_*') ?: []);
    }

    private function fileWithHash(string $hash): File
    {
        $file = $this->createMock(File::class);
        $file->method('getHash')->willReturn($hash);

        return $file;
    }
}
