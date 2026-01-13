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

use demosplan\DemosPlanCoreBundle\Logic\Import\ImportJobProcessor;
use demosplan\DemosPlanCoreBundle\Message\ProcessImportJobsMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessImportJobsMessageHandler
{
    public function __construct(
        private readonly ImportJobProcessor $importJobProcessor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessImportJobsMessage $message): void
    {
        $jobsProcessed = 0;
        try {
            $jobsProcessed = $this->importJobProcessor->processPendingJobs();
        } catch (Exception $e) {
            $this->logger->error('Error processing import jobs', [$e]);
        }
        if ($jobsProcessed > 0) {
            $this->logger->info('Import jobs processed: '.$jobsProcessed, [spl_object_id($message)]);
        }
    }
}
