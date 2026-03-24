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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\PurgeDeletedProceduresMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\PurgeDeletedProceduresMessageHandler;
use Exception;
use Tests\Base\UnitTestCase;

class PurgeDeletedProceduresMessageHandlerTest extends UnitTestCase
{
    use LoggerTestTrait;

    private const PURGE_DELETED_PROCEDURES = 'Purge deleted procedures... ';
    private ?ProcedureHandler $procedureHandler = null;
    private ?GlobalConfigInterface $globalConfig = null;
    private ?PermissionsInterface $permissions = null;
    private ?PurgeDeletedProceduresMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procedureHandler = $this->createMock(ProcedureHandler::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
    }

    public function testInvokeLogsDisabledWhenFeatureDisabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUsePurgeDeletedProcedures')
            ->willReturn(false);

        $this->procedureHandler->expects($this->never())
            ->method('purgeDeletedProcedures');

        $logger = $this->createLoggerMockWithCapture(2);
        $this->sut = new PurgeDeletedProceduresMessageHandler($this->procedureHandler, $this->globalConfig, $this->permissions, $logger);

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());

        // Assert
        $this->assertSame([self::PURGE_DELETED_PROCEDURES, 'Purge deleted procedures is disabled.'], $this->getCapturedLoggerCalls());
    }

    public function testInvokePurgesProceduresWhenEnabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUsePurgeDeletedProcedures')
            ->willReturn(true);

        $this->procedureHandler->expects($this->once())
            ->method('purgeDeletedProcedures')
            ->with(5)
            ->willReturn(3);

        $logger = $this->createLoggerMockWithCapture(3);
        $this->sut = new PurgeDeletedProceduresMessageHandler($this->procedureHandler, $this->globalConfig, $this->permissions, $logger);

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());

        // Assert
        $this->assertSame([self::PURGE_DELETED_PROCEDURES, 'PurgeDeletedProcedures', 'Purged procedures: 3'], $this->getCapturedLoggerCalls());
    }

    public function testInvokeDoesNotLogWhenNoProceduresPurged(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUsePurgeDeletedProcedures')
            ->willReturn(true);

        $this->procedureHandler->expects($this->once())
            ->method('purgeDeletedProcedures')
            ->with(5)
            ->willReturn(0);

        $logger = $this->createLoggerMockWithCapture(2);
        $this->sut = new PurgeDeletedProceduresMessageHandler($this->procedureHandler, $this->globalConfig, $this->permissions, $logger);

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());

        // Assert
        $this->assertSame([self::PURGE_DELETED_PROCEDURES, 'PurgeDeletedProcedures'], $this->getCapturedLoggerCalls());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Purge failed');

        $this->globalConfig->expects($this->once())
            ->method('getUsePurgeDeletedProcedures')
            ->willReturn(true);

        $this->procedureHandler->expects($this->once())
            ->method('purgeDeletedProcedures')
            ->willThrowException($exception);

        $logger = $this->createLoggerMockForError('Purge Procedures failed', $exception);
        $this->sut = new PurgeDeletedProceduresMessageHandler($this->procedureHandler, $this->globalConfig, $this->permissions, $logger);

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());
    }
}
