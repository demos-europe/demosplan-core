<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\Events\AddonMaintenanceEventInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Event\AddonMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\Message\AddonMaintenanceMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class AddonMaintenanceMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AddonMaintenanceMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        try {
            $this->eventDispatcher->dispatch(
                new AddonMaintenanceEvent(),
                AddonMaintenanceEventInterface::class
            );
        } catch (Exception $e) {
            $this->logger->error('Addon Maintenance failed', [$e]);
        }
        $this->logger->info('Finished Addon Maintenance.', [spl_object_id($message)]);
    }
}
