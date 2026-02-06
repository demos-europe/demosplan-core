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
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Message\SwitchElementStatesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SwitchElementStatesMessageHandler;
use Exception;
use Tests\Base\UnitTestCase;

class SwitchElementStatesMessageHandlerTest extends UnitTestCase
{
    use LoggerTestTrait;

    private ?ElementsService $elementService = null;
    private ?PermissionsInterface $permissions = null;
    private ?SwitchElementStatesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->elementService = $this->createMock(ElementsService::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
    }

    public function testInvokeSwitchesElementStatesAndLogs(): void
    {
        // Arrange
        $this->elementService->expects($this->once())
            ->method('autoSwitchElementsState')
            ->willReturn(5);

        $logger = $this->createLoggerMockWithCapture(2);
        $this->sut = new SwitchElementStatesMessageHandler($this->elementService, $this->permissions, $logger);

        // Act
        ($this->sut)(new SwitchElementStatesMessage());

        // Assert
        $this->assertSame(['switchStatesOfToday', 'Switched states of 5 elements.'], $this->getCapturedLoggerCalls());
    }

    public function testInvokeDoesNotLogWhenNoElementsSwitched(): void
    {
        // Arrange
        $this->elementService->expects($this->once())
            ->method('autoSwitchElementsState')
            ->willReturn(0);

        $logger = $this->createLoggerMockWithSingleCall('switchStatesOfToday');
        $this->sut = new SwitchElementStatesMessageHandler($this->elementService, $this->permissions, $logger);

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

        $logger = $this->createLoggerMockForError('switchStatesOfToday failed', $exception);
        $this->sut = new SwitchElementStatesMessageHandler($this->elementService, $this->permissions, $logger);

        // Act
        ($this->sut)(new SwitchElementStatesMessage());
    }
}
