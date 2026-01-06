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

use DemosEurope\DemosplanAddon\Contracts\Events\DailyMaintenanceEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DailyMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\Message\DailyMaintenanceEventMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class DailyMaintenanceEventMessageHandler
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DailyMaintenanceEventMessage $message): void
    {
        try {
            $event = new DailyMaintenanceEvent();
            $this->eventDispatcher->dispatch($event, DailyMaintenanceEventInterface::class);
            $this->logger->info('Daily maintenance event dispatched for addon subscribers', [spl_object_id($message)]);
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: event subscriber(s).', [$exception]);
        }
    }
}
