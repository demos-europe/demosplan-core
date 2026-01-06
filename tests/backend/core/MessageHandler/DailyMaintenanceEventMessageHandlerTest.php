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

use DemosEurope\DemosplanAddon\Contracts\Events\DailyMaintenanceEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DailyMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\Message\DailyMaintenanceEventMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\DailyMaintenanceEventMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Base\UnitTestCase;

class DailyMaintenanceEventMessageHandlerTest extends UnitTestCase
{
    private ?EventDispatcherInterface $eventDispatcher = null;
    private ?LoggerInterface $logger = null;
    private ?DailyMaintenanceEventMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new DailyMaintenanceEventMessageHandler(
            $this->eventDispatcher,
            $this->logger
        );
    }

    public function testInvokeDispatchesEventAndLogsSuccess(): void
    {
        // Arrange
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(DailyMaintenanceEvent::class),
                DailyMaintenanceEventInterface::class
            );

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Daily maintenance event dispatched for addon subscribers');

        // Act
        ($this->sut)(new DailyMaintenanceEventMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Event dispatch failed');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: event subscriber(s).', [$exception]);

        // Act
        ($this->sut)(new DailyMaintenanceEventMessage());
    }
}
