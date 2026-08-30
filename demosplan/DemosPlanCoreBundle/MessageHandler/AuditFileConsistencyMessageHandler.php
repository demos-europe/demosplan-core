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

use demosplan\DemosPlanCoreBundle\Logic\File\FileConsistencyAuditor;
use demosplan\DemosPlanCoreBundle\Message\AuditFileConsistencyMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Writes one log entry per night stating whether database and storage still agree.
 *
 * Scheduled ahead of {@link CleanupFilesMessageHandler} so the entry describes the state the day
 * left behind, before the cleanup deletes orphaned objects and soft deleted rows.
 */
#[AsMessageHandler]
final class AuditFileConsistencyMessageHandler
{
    public function __construct(
        private readonly FileConsistencyAuditor $fileConsistencyAuditor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AuditFileConsistencyMessage $message): void
    {
        try {
            $report = $this->fileConsistencyAuditor->audit();
        } catch (Throwable $e) {
            $this->logger->error('Daily maintenance task failed for: file consistency audit.', [$e]);

            return;
        }

        if ($report->hasFindings()) {
            $this->logger->warning('File consistency audit found inconsistencies', $report->toLogContext());

            return;
        }

        $this->logger->info('File consistency audit found no inconsistencies', $report->toLogContext());
    }
}
