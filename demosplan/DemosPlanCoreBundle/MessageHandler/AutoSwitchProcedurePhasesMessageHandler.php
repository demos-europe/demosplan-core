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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\AutoSwitchProcedurePhasesMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AutoSwitchProcedurePhasesMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public function __construct(
        private readonly ProcedureHandler $procedureHandler,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AutoSwitchProcedurePhasesMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        if (!$this->permissions->hasPermission('feature_auto_switch_to_procedure_end_phase')) {
            $this->logger->info('Skipping auto-switch to evaluation phase: permission not granted');

            return;
        }

        try {
            $this->logger->info('Maintenance: switchToEvaluationPhasesOnEndOfParticipationPhase()', [spl_object_id($message)]);
            $this->procedureHandler->switchToEvaluationPhasesOnEndOfParticipationPhase();
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: switchToEvaluationPhasesOnEndOfParticipationPhase.', [$exception]);
        }
    }
}
