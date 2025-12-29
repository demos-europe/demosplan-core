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

use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\SendDeadlineNotificationsMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendDeadlineNotificationsMessageHandler
{
    public function __construct(
        private readonly ProcedureHandler $procedureHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendDeadlineNotificationsMessage $message): void
    {
        try {
            $this->logger->info('Maintenance: sendNotificationEmailOfDeadlineForPublicAgencies', [spl_object_id($message)]);
            $this->procedureHandler->sendNotificationEmailOfDeadlineForPublicAgencies();
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: sendNotificationEmailOfDeadlineForPublicAgencies.', [$exception]);
        }
    }
}
