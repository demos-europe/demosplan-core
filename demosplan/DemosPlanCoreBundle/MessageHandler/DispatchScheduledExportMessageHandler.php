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

use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobMaintenance;
use demosplan\DemosPlanCoreBundle\Message\MaintainExportJobsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

/**
 * Closes out abandoned export jobs and deletes export results past their retention window.
 */
#[AsMessageHandler]
final class DispatchScheduledExportMessageHandler
{
    public function __construct(
        private readonly ExportJobMaintenance $exportJobMaintenance,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MaintainExportJobsMessage $message): void
    {
        try {
            $this->exportJobMaintenance->failStaleJobs();
        } catch (Throwable $exception) {
            $this->logger->error('Maintenance: failed to close out abandoned export jobs', [$exception]);
        }

        try {
            $this->exportJobMaintenance->purgeExpiredResults();
        } catch (Throwable $exception) {
            $this->logger->error('Maintenance: failed to purge expired export jobs', [$exception]);
        }
    }
}
