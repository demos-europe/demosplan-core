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

use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Message\PurgeSentEmailsMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeSentEmailsMessageHandler
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeSentEmailsMessage $message): void
    {
        try {
            $deleted = $this->mailService->deleteAfterDays((int) $this->parameterBag->get('email_delete_after_days'));
            $this->logger->info("Deleted $deleted old emails");
        } catch (Exception $e) {
            $this->logger->error('Daily maintenance task failed for: Delete old emails', [$e]);
        }
    }
}
