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

use DemosEurope\DemosplanAddon\Contracts\Events\AddonMaintenanceEventInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Event\AddonMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\Message\AddonMaintenanceMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\AddonMaintenanceMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Base\UnitTestCase;

class AddonMaintenanceMessageHandlerTest extends UnitTestCase
{
    private ?EventDispatcherInterface $eventDispatcher = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?AddonMaintenanceMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new AddonMaintenanceMessageHandler(
            $this->eventDispatcher,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeDispatchesEventAndLogs(): void
    {
        // Arrange
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(AddonMaintenanceEvent::class),
                AddonMaintenanceEventInterface::class
            );

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Finished Addon Maintenance.');

        // Act
        ($this->sut)(new AddonMaintenanceMessage());
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
            ->with('Addon Maintenance failed', [$exception]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Finished Addon Maintenance.');

        // Act
        ($this->sut)(new AddonMaintenanceMessage());
    }
}
