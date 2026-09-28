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
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentDeadlineReminderService;
use demosplan\DemosPlanCoreBundle\Message\SendSegmentDeadlineReminderEmailsMessage;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendSegmentDeadlineReminderEmailsMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly SegmentDeadlineReminderService $segmentDeadlineReminderService,
    ) {
    }

    public function __invoke(SendSegmentDeadlineReminderEmailsMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        try {
            $this->segmentDeadlineReminderService->sendSegmentDeadlineReminderMails();
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: sendSegmentDeadlineReminderMails.', [$exception]);
        }
    }
}
