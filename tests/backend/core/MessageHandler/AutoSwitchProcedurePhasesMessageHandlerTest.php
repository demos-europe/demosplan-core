<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\AutoSwitchProcedurePhasesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\AutoSwitchProcedurePhasesMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class AutoSwitchProcedurePhasesMessageHandlerTest extends UnitTestCase
{
    private ?ProcedureHandler $procedureHandler = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?AutoSwitchProcedurePhasesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedureHandler = $this->createMock(ProcedureHandler::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new AutoSwitchProcedurePhasesMessageHandler(
            $this->procedureHandler,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeSkipsWhenPermissionNotGranted(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_auto_switch_to_procedure_end_phase')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Skipping auto-switch to evaluation phase: permission not granted');

        $this->procedureHandler->expects($this->never())
            ->method('switchToEvaluationPhasesOnEndOfParticipationPhase');

        // Act
        ($this->sut)(new AutoSwitchProcedurePhasesMessage());
    }

    public function testInvokeSwitchesPhasesAndLogsSuccess(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_auto_switch_to_procedure_end_phase')
            ->willReturn(true);

        $this->procedureHandler->expects($this->once())
            ->method('switchToEvaluationPhasesOnEndOfParticipationPhase');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Maintenance: switchToEvaluationPhasesOnEndOfParticipationPhase()');

        // Act
        ($this->sut)(new AutoSwitchProcedurePhasesMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Switching phases failed');

        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_auto_switch_to_procedure_end_phase')
            ->willReturn(true);

        $this->procedureHandler->expects($this->once())
            ->method('switchToEvaluationPhasesOnEndOfParticipationPhase')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: switchToEvaluationPhasesOnEndOfParticipationPhase.', [$exception]);

        // Act
        ($this->sut)(new AutoSwitchProcedurePhasesMessage());
    }
}
