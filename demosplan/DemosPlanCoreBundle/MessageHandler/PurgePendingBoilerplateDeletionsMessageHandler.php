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

use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\PurgePendingBoilerplateDeletionsMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgePendingBoilerplateDeletionsMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    private const BATCH_LIMIT = 5;

    public function __construct(
        private readonly ProcedureHandler $procedureHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgePendingBoilerplateDeletionsMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        try {
            $purgedCount = $this->procedureHandler->purgePendingBoilerplateDeletions(self::BATCH_LIMIT);
        } catch (Exception $e) {
            $this->logger->error('Purge pending boilerplate deletions failed', [$e]);

            return;
        }

        if ($purgedCount > 0) {
            $this->logger->info('Purged pending boilerplate deletions: '.$purgedCount);
        }
    }
}
