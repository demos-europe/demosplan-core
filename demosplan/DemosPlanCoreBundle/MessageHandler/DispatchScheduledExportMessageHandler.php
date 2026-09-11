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

use demosplan\DemosPlanCoreBundle\Logic\Export\ScheduledExportDispatcher;
use demosplan\DemosPlanCoreBundle\Message\DispatchScheduledExportMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Reacts to the daily scheduler tick and starts a run for every due xlsx export schedule.
 */
#[AsMessageHandler]
final class DispatchScheduledExportMessageHandler
{
    public function __construct(
        private readonly ScheduledExportDispatcher $scheduledExportDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchScheduledExportMessage $message): void
    {
        try {
            $this->scheduledExportDispatcher->dispatchDueExports();
        } catch (Throwable $exception) {
            $this->logger->error('Maintenance: Failed to dispatch due scheduled xlsx exports', [$exception]);
        }
    }
}
