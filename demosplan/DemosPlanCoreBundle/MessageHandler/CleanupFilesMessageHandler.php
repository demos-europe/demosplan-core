<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Message\CleanupFilesMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanupFilesMessageHandler
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CleanupFilesMessage $message): void
    {
        if (!$this->globalConfig->doDeleteRemovedFiles()) {
            $this->logger->info('Skipping file cleanup: doDeleteRemovedFiles is disabled');

            return;
        }

        try {
            $this->logger->info('Maintenance: remove soft deleted Files');
            $filesDeleted = $this->fileService->deleteSoftDeletedFiles();
            $this->logger->info('Maintenance: Soft deleted files deleted: ', [$filesDeleted]);

            $this->logger->info('Maintenance: remove orphaned Files');
            $filesDeleted = $this->fileService->removeOrphanedFiles();
            $this->logger->info('Maintenance: Orphaned Files deleted: ', [$filesDeleted]);

            $this->logger->info('Maintenance: remove temporary upload Files');
            $filesDeleted = $this->fileService->removeTemporaryUploadFiles();
            $this->logger->info('Maintenance: Temporary Uploaded Files deleted: ', [$filesDeleted]);

            $this->logger->info('Maintenance: check for deleted Files');
            $this->fileService->checkDeletedFiles();
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: delete obsolete files.', [$exception]);
        }
    }
}
