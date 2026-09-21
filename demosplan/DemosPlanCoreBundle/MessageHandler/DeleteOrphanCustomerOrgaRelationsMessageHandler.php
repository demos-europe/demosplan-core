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

use demosplan\DemosPlanCoreBundle\Message\DeleteOrphanCustomerOrgaRelationsMessage;
use demosplan\DemosPlanCoreBundle\Repository\OrgaStatusInCustomerRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final class DeleteOrphanCustomerOrgaRelationsMessageHandler
{
    public function __construct(
        private readonly OrgaStatusInCustomerRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteOrphanCustomerOrgaRelationsMessage $message): void
    {
        try {
            $deleted = $this->repository->deleteOrphanedOrgaRelations();

            if ($deleted > 0) {
                $this->logger->info('Maintenance: deleted orphaned customer/orga relations', ['deleted' => $deleted]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Daily maintenance task failed for: orphaned customer/orga relations cleanup.', [$e]);
        }
    }
}
