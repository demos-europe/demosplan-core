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
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Message\FetchStatementGeoDataMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\FetchStatementGeoDataMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class FetchStatementGeoDataMessageHandlerTest extends UnitTestCase
{
    private ?StatementService $statementService = null;
    private ?GlobalConfigInterface $globalConfig = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?FetchStatementGeoDataMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statementService = $this->createMock(StatementService::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new FetchStatementGeoDataMessageHandler(
            $this->statementService,
            $this->globalConfig,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeDoesNothingWhenFeatureDisabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUseFetchAdditionalGeodata')
            ->willReturn(false);

        $this->statementService->expects($this->never())
            ->method('processScheduledFetchGeoData');

        // Act
        ($this->sut)(new FetchStatementGeoDataMessage());
    }

    public function testInvokeFetchesGeoDataWhenEnabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUseFetchAdditionalGeodata')
            ->willReturn(true);

        $this->statementService->expects($this->once())
            ->method('processScheduledFetchGeoData')
            ->willReturn(2);

        $loggerCalls = [];
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$loggerCalls) {
                $loggerCalls[] = $message;
            });

        // Act
        ($this->sut)(new FetchStatementGeoDataMessage());

        // Assert
        $this->assertSame(['Fetch Statement Geodata... ', 'Statement Geodata fetched: 2'], $loggerCalls);
    }

    public function testInvokeDoesNotLogWhenNoGeoDataFetched(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('getUseFetchAdditionalGeodata')
            ->willReturn(true);

        $this->statementService->expects($this->once())
            ->method('processScheduledFetchGeoData')
            ->willReturn(0);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Fetch Statement Geodata... ');

        // Act
        ($this->sut)(new FetchStatementGeoDataMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Geodata fetch failed');

        $this->globalConfig->expects($this->once())
            ->method('getUseFetchAdditionalGeodata')
            ->willReturn(true);

        $this->statementService->expects($this->once())
            ->method('processScheduledFetchGeoData')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('FetchGeodata failed', [$exception]);

        // Act
        ($this->sut)(new FetchStatementGeoDataMessage());
    }
}
