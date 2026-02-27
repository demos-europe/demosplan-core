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

use demosplan\DemosPlanCoreBundle\Message\PurgeExpiredOAuthTokensMessage;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeExpiredOAuthTokensMessageHandler
{
    public function __construct(
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeExpiredOAuthTokensMessage $message): void
    {
        try {
            $deleted = $this->oauthTokenRepository->clearOutdated();
            $this->logger->info('Maintenance: purged expired OAuth token entries', ['deleted' => $deleted]);
        } catch (Exception $exception) {
            $this->logger->error('Maintenance: failed to purge expired OAuth token entries', [$exception]);
        }
    }
}
