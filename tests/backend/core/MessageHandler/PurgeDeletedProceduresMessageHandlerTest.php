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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\PurgeDeletedProceduresMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\PurgeDeletedProceduresMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class PurgeDeletedProceduresMessageHandlerTest extends UnitTestCase
{
    private ?ProcedureHandler $procedureHandler = null;
    private ?GlobalConfigInterface $globalConfig = null;
    private ?LoggerInterface $logger = null;
    private ?PurgeDeletedProceduresMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedureHandler = $this->createMock(ProcedureHandler::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new PurgeDeletedProceduresMessageHandler(
            $this->procedureHandler,
            $this->globalConfig,
            $this->logger
        );
    }

    public function testInvokeLogsDisabledWhenFeatureDisabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUsePurgeDeletedProcedures')
            ->willReturn(false);

        $this->procedureHandler->expects($this->never())
            ->method('purgeDeletedProcedures');

        $loggerCalls = [];
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$loggerCalls) {
                $loggerCalls[] = $message;
            });

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());

        // Assert
        $this->assertSame(['Purge deleted procedures... ', 'Purge deleted procedures is disabled.'], $loggerCalls);
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

        $loggerCalls = [];
        $this->logger->expects($this->exactly(3))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$loggerCalls) {
                $loggerCalls[] = $message;
            });

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());

        // Assert
        $this->assertSame(['Purge deleted procedures... ', 'PurgeDeletedProcedures', 'Purged procedures: 3'], $loggerCalls);
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

        $loggerCalls = [];
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$loggerCalls) {
                $loggerCalls[] = $message;
            });

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());

        // Assert
        $this->assertSame(['Purge deleted procedures... ', 'PurgeDeletedProcedures'], $loggerCalls);
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

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Purge Procedures failed', [$exception]);

        // Act
        ($this->sut)(new PurgeDeletedProceduresMessage());
    }
}
