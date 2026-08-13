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

use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJobInterface;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobDownloadResponseFactory;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Base\UnitTestCase;

class ExportJobDownloadResponseFactoryTest extends UnitTestCase
{
    private const ABSOLUTE_PATH = 'procedure/hash-1';

    protected ?ExportJobDownloadResponseFactory $sut = null;

    private ?FileService $fileServiceMock = null;
    private ?FilesystemOperator $defaultStorageMock = null;
    private ?FilesystemOperator $localStorageMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->fileServiceMock->method('getFileInfo')->willReturn(new FileInfo(
            'hash-1',
            'stored-name.zip',
            1234,
            'application/zip',
            'procedure',
            self::ABSOLUTE_PATH,
            null
        ));

        $this->defaultStorageMock = $this->createMock(FilesystemOperator::class);
        $this->localStorageMock = $this->createMock(FilesystemOperator::class);

        $this->sut = new ExportJobDownloadResponseFactory(
            $this->fileServiceMock,
            $this->defaultStorageMock,
            $this->localStorageMock
        );
    }

    public function testCreateStreamsFromDefaultStorage(): void
    {
        // Arrange
        $this->defaultStorageMock->method('fileExists')->willReturn(true);
        $this->defaultStorageMock->expects($this->once())
            ->method('readStream')
            ->with(self::ABSOLUTE_PATH)
            ->willReturn($this->streamContaining('zip-bytes'));
        $this->localStorageMock->expects($this->never())->method('readStream');

        // Act
        $response = $this->sut->createForJob($this->completedJob('Verfahrensexport.zip'));

        // Assert
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame(
            'attachment; filename="Verfahrensexport.zip"',
            $response->headers->get('Content-Disposition')
        );
        self::assertSame('zip-bytes', $this->sendAndCapture($response));
    }

    public function testCreateFallsBackToLocalStorage(): void
    {
        // Arrange - exports written by a worker may still sit on the local disk
        $this->defaultStorageMock->method('fileExists')->willReturn(false);
        $this->localStorageMock->method('fileExists')->willReturn(true);
        $this->localStorageMock->expects($this->once())
            ->method('readStream')
            ->willReturn($this->streamContaining('zip-bytes'));

        // Act
        $response = $this->sut->createForJob($this->completedJob('Verfahrensexport.zip'));

        // Assert
        self::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testCreateFallsBackToTheStoredFileNameWithoutJobFileName(): void
    {
        // Arrange
        $this->defaultStorageMock->method('fileExists')->willReturn(true);
        $this->defaultStorageMock->method('readStream')->willReturn($this->streamContaining('zip-bytes'));

        // Act
        $response = $this->sut->createForJob($this->completedJob(null));

        // Assert
        self::assertSame(
            'attachment; filename="stored-name.zip"',
            $response?->headers->get('Content-Disposition')
        );
    }

    public function testCreateReturnsNullWhenTheFileIsGoneFromBothStorages(): void
    {
        // Arrange - the maintenance sweep removes the files of expired jobs
        $this->defaultStorageMock->method('fileExists')->willReturn(false);
        $this->localStorageMock->method('fileExists')->willReturn(false);

        // Act & Assert
        self::assertNull($this->sut->createForJob($this->completedJob('Verfahrensexport.zip')));
    }

    public function testCreateReturnsNullWithoutFileHash(): void
    {
        // Arrange
        $job = $this->createMock(AsyncExportJobInterface::class);
        $job->method('getFileHash')->willReturn(null);

        // Act & Assert
        self::assertNull($this->sut->createForJob($job));
    }

    private function completedJob(?string $fileName): AsyncExportJobInterface
    {
        $job = $this->createMock(AsyncExportJobInterface::class);
        $job->method('getFileHash')->willReturn('hash-1');
        $job->method('getFileName')->willReturn($fileName);

        return $job;
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

    private function sendAndCapture(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
