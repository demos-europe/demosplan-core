<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Message\CleanupFilesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\CleanupFilesMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class CleanupFilesMessageHandlerTest extends UnitTestCase
{
    private ?FileService $fileService = null;
    private ?GlobalConfigInterface $globalConfig = null;
    private ?LoggerInterface $logger = null;
    private ?CleanupFilesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileService = $this->createMock(FileService::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new CleanupFilesMessageHandler(
            $this->fileService,
            $this->globalConfig,
            $this->logger
        );
    }

    public function testInvokeSkipsWhenConfigDisabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('doDeleteRemovedFiles')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Skipping file cleanup: doDeleteRemovedFiles is disabled');

        $this->fileService->expects($this->never())
            ->method('deleteSoftDeletedFiles');

        // Act
        ($this->sut)(new CleanupFilesMessage());
    }

    public function testInvokeCleansFilesAndLogsSuccess(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('doDeleteRemovedFiles')
            ->willReturn(true);

        $this->fileService->expects($this->once())
            ->method('deleteSoftDeletedFiles')
            ->willReturn(5);

        $this->fileService->expects($this->once())
            ->method('removeOrphanedFiles')
            ->willReturn(3);

        $this->fileService->expects($this->once())
            ->method('removeTemporaryUploadFiles')
            ->willReturn(7);

        $this->fileService->expects($this->once())
            ->method('checkDeletedFiles');

        $logMessages = [];
        $this->logger->expects($this->exactly(7))
            ->method('info')
            ->willReturnCallback(function ($message, $context = []) use (&$logMessages) {
                $logMessages[] = ['message' => $message, 'context' => $context];
            });

        // Act
        ($this->sut)(new CleanupFilesMessage());

        // Assert
        $this->assertCount(7, $logMessages);
        $this->assertSame('Maintenance: remove soft deleted Files', $logMessages[0]['message']);
        $this->assertSame('Maintenance: Soft deleted files deleted: ', $logMessages[1]['message']);
        $this->assertSame([5], $logMessages[1]['context']);
        $this->assertSame('Maintenance: remove orphaned Files', $logMessages[2]['message']);
        $this->assertSame('Maintenance: Orphaned Files deleted: ', $logMessages[3]['message']);
        $this->assertSame([3], $logMessages[3]['context']);
        $this->assertSame('Maintenance: remove temporary upload Files', $logMessages[4]['message']);
        $this->assertSame('Maintenance: Temporary Uploaded Files deleted: ', $logMessages[5]['message']);
        $this->assertSame([7], $logMessages[5]['context']);
        $this->assertSame('Maintenance: check for deleted Files', $logMessages[6]['message']);
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('File cleanup failed');

        $this->globalConfig->expects($this->once())
            ->method('doDeleteRemovedFiles')
            ->willReturn(true);

        $this->fileService->expects($this->once())
            ->method('deleteSoftDeletedFiles')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: delete obsolete files.', [$exception]);

        // Act
        ($this->sut)(new CleanupFilesMessage());
    }
}
