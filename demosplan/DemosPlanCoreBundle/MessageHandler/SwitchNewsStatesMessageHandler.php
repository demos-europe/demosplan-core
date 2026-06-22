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
use demosplan\DemosPlanCoreBundle\Exception\NoDesignatedStateException;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Message\SwitchNewsStatesMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SwitchNewsStatesMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public function __construct(
        private readonly ProcedureNewsService $procedureNewsService,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SwitchNewsStatesMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        try {
            $this->logger->info('Maintenance: switchStatesOfNewsOfToday', [spl_object_id($message)]);
            $this->setStateOfNewsOfToday();
        } catch (Exception $e) {
            $this->logger->error('Daily maintenance task failed for: switching of news state.', [$e]);
        }
    }

    private function setStateOfNewsOfToday(): void
    {
        $newsToSetState = $this->procedureNewsService->getNewsToSetStateToday();

        $successfulSwitches = 0;
        foreach ($newsToSetState as $news) {
            try {
                $this->procedureNewsService->setState($news);
                ++$successfulSwitches;
            } catch (NoDesignatedStateException $e) {
                $this->logger->error('Set state of news failed, because designated state is not defined.', [$e]);
            } catch (Exception $e) {
                $this->logger->error("Set state of the news with ID {$news->getId()} failed", [$e]);
            }
        }

        $this->logger->info("Set states of {$successfulSwitches} news.");
    }
}
