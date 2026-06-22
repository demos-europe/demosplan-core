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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Message\SendAssignedTaskNotificationEmailsMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SendAssignedTaskNotificationEmailsMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class SendAssignedTaskNotificationEmailsMessageHandlerTest extends UnitTestCase
{
    private ?EntityContentChangeService $entityContentChangeService = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?SendAssignedTaskNotificationEmailsMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityContentChangeService = $this->createMock(EntityContentChangeService::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new SendAssignedTaskNotificationEmailsMessageHandler(
            $this->entityContentChangeService,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeSkipsWhenPermissionNotGranted(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_send_assigned_task_notification_email')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Skipping assigned task notification emails: permission not granted');

        $this->entityContentChangeService->expects($this->never())
            ->method('sendAssignedTaskNotificationMails');

        // Act
        ($this->sut)(new SendAssignedTaskNotificationEmailsMessage());
    }

    public function testInvokeSendsNotificationsAndLogsSuccess(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_send_assigned_task_notification_email')
            ->willReturn(true);

        $this->entityContentChangeService->expects($this->once())
            ->method('sendAssignedTaskNotificationMails')
            ->with(Segment::class)
            ->willReturn(5);

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context = []) {
                if ('Maintenance: sendAssignedTaskNotificationMails()' === $message) {
                    $this->assertNotEmpty($context); // Expects [spl_object_id($message)]
                } elseif ('Maintenance: sendAssignedTaskNotificationMails(). Number of created mail_send entries:' === $message) {
                    $this->assertSame([5], $context);
                }
            });

        // Act
        ($this->sut)(new SendAssignedTaskNotificationEmailsMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Sending notification emails failed');

        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_send_assigned_task_notification_email')
            ->willReturn(true);

        $this->entityContentChangeService->expects($this->once())
            ->method('sendAssignedTaskNotificationMails')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: sendAssignedTaskNotificationMails.', [$exception]);

        // Act
        ($this->sut)(new SendAssignedTaskNotificationEmailsMessage());
    }
}
