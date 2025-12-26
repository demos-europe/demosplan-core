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

use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Message\SwitchElementStatesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SwitchElementStatesMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class SwitchElementStatesMessageHandlerTest extends UnitTestCase
{
    private ?ElementsService $elementService = null;
    private ?LoggerInterface $logger = null;
    private ?SwitchElementStatesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->elementService = $this->createMock(ElementsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new SwitchElementStatesMessageHandler(
            $this->elementService,
            $this->logger
        );
    }

    public function testInvokeSwitchesElementStatesAndLogs(): void
    {
        // Arrange
        $this->elementService->expects($this->once())
            ->method('autoSwitchElementsState')
            ->willReturn(5);

        $loggerCalls = [];
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$loggerCalls) {
                $loggerCalls[] = $message;
            });

        // Act
        ($this->sut)(new SwitchElementStatesMessage());

        // Assert
        $this->assertSame(['switchStatesOfToday', 'Switched states of 5 elements.'], $loggerCalls);
    }

    public function testInvokeDoesNotLogWhenNoElementsSwitched(): void
    {
        // Arrange
        $this->elementService->expects($this->once())
            ->method('autoSwitchElementsState')
            ->willReturn(0);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('switchStatesOfToday');

        // Act
        ($this->sut)(new SwitchElementStatesMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('State switch failed');

        $this->elementService->expects($this->once())
            ->method('autoSwitchElementsState')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('switchStatesOfToday failed', [$exception]);

        // Act
        ($this->sut)(new SwitchElementStatesMessage());
    }
}
