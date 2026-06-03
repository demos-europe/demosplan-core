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
use demosplan\DemosPlanCoreBundle\Message\SendDeadlineNotificationsMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SendDeadlineNotificationsMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class SendDeadlineNotificationsMessageHandlerTest extends UnitTestCase
{
    private ?ProcedureHandler $procedureHandler = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?SendDeadlineNotificationsMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedureHandler = $this->createMock(ProcedureHandler::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new SendDeadlineNotificationsMessageHandler(
            $this->procedureHandler,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeSendsNotificationsAndLogsSuccess(): void
    {
        // Arrange
        $this->procedureHandler->expects($this->once())
            ->method('sendNotificationEmailOfDeadlineForPublicAgencies');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Maintenance: sendNotificationEmailOfDeadlineForPublicAgencies');

        // Act
        ($this->sut)(new SendDeadlineNotificationsMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Sending notifications failed');

        $this->procedureHandler->expects($this->once())
            ->method('sendNotificationEmailOfDeadlineForPublicAgencies')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: sendNotificationEmailOfDeadlineForPublicAgencies.', [$exception]);

        // Act
        ($this->sut)(new SendDeadlineNotificationsMessage());
    }
}
