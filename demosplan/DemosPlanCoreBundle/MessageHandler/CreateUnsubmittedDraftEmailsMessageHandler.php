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
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use demosplan\DemosPlanCoreBundle\Message\CreateUnsubmittedDraftEmailsMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateUnsubmittedDraftEmailsMessageHandler
{
    private const DAYS_BEFORE_DEADLINE = 7;

    public function __construct(
        private readonly DraftStatementHandler $draftStatementHandler,
        private readonly PermissionsInterface $permissions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateUnsubmittedDraftEmailsMessage $message): void
    {
        if (!$this->permissions->hasPermission('feature_send_email_on_procedure_ending_phase_send_mails')) {
            $this->logger->info('Skipping unsubmitted draft emails: permission not granted');

            return;
        }

        try {
            $this->logger->info('Maintenance: createMailsForUnsubmittedDraftsInSoonEndingProcedures()', [spl_object_id($message)]);
            $numberOfCreatedMails = $this->createMailsForUnsubmittedDrafts(self::DAYS_BEFORE_DEADLINE);
            $this->logger->info('Maintenance: createMailsForUnsubmittedDraftsInSoonEndingProcedures(). Number of created mail_send entries:', [$numberOfCreatedMails]);
        } catch (Exception $exception) {
            $this->logger->error('Daily maintenance task failed for: createMailsForUnsubmittedDraftsInSoonEndingProcedures.', [$exception]);
        }
    }

    private function createMailsForUnsubmittedDrafts(int $exactlyDaysToGo): int
    {
        $numberOfCreatedExternalMails = $this->draftStatementHandler
            ->createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser($exactlyDaysToGo, false);

        $numberOfCreatedInternalMails = $this->draftStatementHandler
            ->createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser($exactlyDaysToGo, true);

        return $numberOfCreatedExternalMails + $numberOfCreatedInternalMails;
    }
}
