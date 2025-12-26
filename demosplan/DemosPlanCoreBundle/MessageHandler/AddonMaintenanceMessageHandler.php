<?php

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\Events\AddonMaintenanceEventInterface;
use demosplan\DemosPlanCoreBundle\Event\AddonMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\Message\AddonMaintenanceMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class AddonMaintenanceMessageHandler
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(AddonMaintenanceMessage $message): void
    {
        try {
            $this->eventDispatcher->dispatch(
                new AddonMaintenanceEvent(),
                AddonMaintenanceEventInterface::class
            );
        } catch (Exception $e) {
            $this->logger->error('Addon Maintenance failed', [$e]);
        }
        $this->logger->info('Finished Addon Maintenance.');
    }
}
