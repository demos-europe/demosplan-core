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

use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Message\SwitchProcedurePhasesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SwitchProcedurePhasesMessageHandler;
use Exception;
use Tests\Base\UnitTestCase;

class SwitchProcedurePhasesMessageHandlerTest extends UnitTestCase
{
    use LoggerTestTrait;

    private ?ProcedureService $procedureService = null;
    private ?SwitchProcedurePhasesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procedureService = $this->createMock(ProcedureService::class);
    }

    public function testInvokeSwitchesPhasesAndLogs(): void
    {
        // Arrange
        $this->procedureService->expects($this->once())
            ->method('switchPhasesOfProceduresUntilNow')
            ->willReturn([3, 2]);

        $logger = $this->createLoggerMockWithCapture(3);
        $this->sut = new SwitchProcedurePhasesMessageHandler($this->procedureService, $logger);

        // Act
        ($this->sut)(new SwitchProcedurePhasesMessage());

        // Assert
        $this->assertSame([
            'switchPhasesOfToday',
            'Switched phases of 3 internal/public agency procedures.',
            'Switched phases of 2 external/citizen procedures.',
        ], $this->getCapturedLoggerCalls());
    }

    public function testInvokeDoesNotLogWhenNoProceduresSwitched(): void
    {
        // Arrange
        $this->procedureService->expects($this->once())
            ->method('switchPhasesOfProceduresUntilNow')
            ->willReturn([0, 0]);

        $logger = $this->createLoggerMockWithSingleCall('switchPhasesOfToday');
        $this->sut = new SwitchProcedurePhasesMessageHandler($this->procedureService, $logger);

        // Act
        ($this->sut)(new SwitchProcedurePhasesMessage());
    }

    public function testInvokeLogsSwitchWhenOnlyInternalProceduresSwitched(): void
    {
        // Arrange
        $this->procedureService->expects($this->once())
            ->method('switchPhasesOfProceduresUntilNow')
            ->willReturn([5, 0]);

        $logger = $this->createLoggerMockWithCapture(3);
        $this->sut = new SwitchProcedurePhasesMessageHandler($this->procedureService, $logger);

        // Act
        ($this->sut)(new SwitchProcedurePhasesMessage());

        // Assert
        $this->assertSame([
            'switchPhasesOfToday',
            'Switched phases of 5 internal/public agency procedures.',
            'Switched phases of 0 external/citizen procedures.',
        ], $this->getCapturedLoggerCalls());
    }

    public function testInvokeLogsSwitchWhenOnlyExternalProceduresSwitched(): void
    {
        // Arrange
        $this->procedureService->expects($this->once())
            ->method('switchPhasesOfProceduresUntilNow')
            ->willReturn([0, 4]);

        $logger = $this->createLoggerMockWithCapture(3);
        $this->sut = new SwitchProcedurePhasesMessageHandler($this->procedureService, $logger);

        // Act
        ($this->sut)(new SwitchProcedurePhasesMessage());

        // Assert
        $this->assertSame([
            'switchPhasesOfToday',
            'Switched phases of 0 internal/public agency procedures.',
            'Switched phases of 4 external/citizen procedures.',
        ], $this->getCapturedLoggerCalls());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Phase switch failed');

        $this->procedureService->expects($this->once())
            ->method('switchPhasesOfProceduresUntilNow')
            ->willThrowException($exception);

        $logger = $this->createLoggerMockForError('switchPhasesOfToday failed', $exception);
        $this->sut = new SwitchProcedurePhasesMessageHandler($this->procedureService, $logger);

        // Act
        ($this->sut)(new SwitchProcedurePhasesMessage());
    }
}
