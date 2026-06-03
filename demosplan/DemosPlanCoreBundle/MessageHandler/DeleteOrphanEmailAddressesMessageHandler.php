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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use demosplan\DemosPlanCoreBundle\Message\DeleteOrphanEmailAddressesMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteOrphanEmailAddressesMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public function __construct(
        private readonly EmailAddressService $emailAddressService,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteOrphanEmailAddressesMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        try {
            $this->logger->info('Maintenance: deleteOrphanEmailAddresses()', [spl_object_id($message)]);
            $numberOfDeletedEmailAddresses = $this->emailAddressService->deleteOrphanEmailAddresses();
            $this->logger->info("Deleted $numberOfDeletedEmailAddresses orphan email addresses");
        } catch (Exception $e) {
            $this->logger->error('Daily maintenance task failed for: removing orphan email addresses.', [$e]);
        }
    }
}
