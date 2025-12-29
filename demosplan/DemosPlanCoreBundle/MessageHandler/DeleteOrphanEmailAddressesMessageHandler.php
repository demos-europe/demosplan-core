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

use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use demosplan\DemosPlanCoreBundle\Message\DeleteOrphanEmailAddressesMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteOrphanEmailAddressesMessageHandler
{
    public function __construct(
        private readonly EmailAddressService $emailAddressService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteOrphanEmailAddressesMessage $message): void
    {
        try {
            $this->logger->info('Maintenance: deleteOrphanEmailAddresses()', [spl_object_id($message)]);
            $numberOfDeletedEmailAddresses = $this->emailAddressService->deleteOrphanEmailAddresses();
            $this->logger->info("Deleted $numberOfDeletedEmailAddresses orphan email addresses");
        } catch (Exception $e) {
            $this->logger->error('Daily maintenance task failed for: removing orphan email addresses.', [$e]);
        }
    }
}
