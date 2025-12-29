<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Message\SwitchElementStatesMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SwitchElementStatesMessageHandler
{
    public function __construct(
        private readonly ElementsService $elementService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SwitchElementStatesMessage $message): void
    {
        $this->logger->info('switchStatesOfToday');

        $affectedElements = 0;

        try {
            $affectedElements = $this->elementService->autoSwitchElementsState();
        } catch (Exception $e) {
            $this->logger->error('switchStatesOfToday failed', [$e]);
        }

        if ($affectedElements > 0) {
            $this->logger->info("Switched states of $affectedElements elements.", [spl_object_id($message)]);
        }
    }
}
