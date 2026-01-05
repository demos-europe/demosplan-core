<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Message\SwitchProcedurePhasesMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SwitchProcedurePhasesMessageHandler
{
    public function __construct(
        private readonly ProcedureService $procedureService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SwitchProcedurePhasesMessage $message): void
    {
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
