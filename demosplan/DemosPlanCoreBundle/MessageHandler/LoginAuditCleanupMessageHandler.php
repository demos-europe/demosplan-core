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

use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Message\LoginAuditCleanupMessage;
use demosplan\DemosPlanCoreBundle\Repository\LoginAuditRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final class LoginAuditCleanupMessageHandler
{
    public function __construct(
        private readonly LoginAuditRepository $repository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LoginAuditCleanupMessage $message): void
    {
        $days = (int) $this->parameterBag->get('login_audit_retention_days');
        if ($days <= 0) {
            $this->logger->warning('Skipping login_audit cleanup: retention is not configured or non-positive', ['days' => $days]);

            return;
        }

        $cutoff = (new DateTimeImmutable())->modify(sprintf('-%d days', $days));

        try {
            $deleted = $this->repository->deleteOlderThan($cutoff);
            $this->logger->info('Maintenance: login_audit cleanup', [
                'deleted' => $deleted,
                'cutoff'  => $cutoff->format(DATE_ATOM),
                'days'    => $days,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Daily maintenance task failed for: login_audit cleanup.', [$e]);
        }
    }
}
