<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Message\SendAssignedTaskNotificationEmailsMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendAssignedTaskNotificationEmailsMessageHandler
{
    public function __construct(
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendAssignedTaskNotificationEmailsMessage $message): void
    {
        if (!$this->permissions->hasPermission('feature_send_assigned_task_notification_email')) {
            $this->logger->info('Skipping assigned task notification emails: permission not granted');

            return;
        }

        try {
            $this->logger->info('Maintenance: sendAssignedTaskNotificationMails()');
            $numberOfCreatedNotificationMails = $this->entityContentChangeService->sendAssignedTaskNotificationMails(Segment::class);
            $this->logger->info('Maintenance: sendAssignedTaskNotificationMails(). Number of created mail_send entries:', [$numberOfCreatedNotificationMails]);
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: sendAssignedTaskNotificationMails.', [$exception]);
        }
    }
}
