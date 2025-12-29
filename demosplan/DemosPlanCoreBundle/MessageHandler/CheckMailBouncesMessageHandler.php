<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\BounceChecker;
use demosplan\DemosPlanCoreBundle\Message\CheckMailBouncesMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckMailBouncesMessageHandler
{
    public function __construct(
        private readonly BounceChecker $bounceChecker,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckMailBouncesMessage $message): void
    {
        if (!$this->globalConfig->doEmailBounceCheck()) {
            return;
        }

        $bouncesProcessed = 0;
        try {
            $bouncesProcessed = $this->bounceChecker->checkEmailBounces();
            $this->logger->info('Emailbounces');
        } catch (Exception $e) {
            $this->logger->error('Emailbounces failed', [$e]);
        }

        if ($bouncesProcessed > 0) {
            $this->logger->info('Emailbounces processed: '.$bouncesProcessed);
        }
    }
}
