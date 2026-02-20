<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Message\SwitchProcedurePhasesMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SwitchProcedurePhasesMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public function __construct(
        private readonly ProcedureService $procedureService,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SwitchProcedurePhasesMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        $this->logger->info('switchPhasesOfToday');

        $internalProcedureCounter = 0;
        $externalProcedureCounter = 0;
        try {
            [$internalProcedureCounter, $externalProcedureCounter] = $this->procedureService->switchPhasesOfProceduresUntilNow();
        } catch (Exception $e) {
            $this->logger->error('switchPhasesOfToday failed', [$e, spl_object_id($message)]);
        }

        if ($internalProcedureCounter > 0 || $externalProcedureCounter > 0) {
            $switchedStr = 'Switched phases of ';
            $this->logger->info($switchedStr.$internalProcedureCounter.' internal/public agency procedures.');
            $this->logger->info($switchedStr.$externalProcedureCounter.' external/citizen procedures.');
        }
    }
}
