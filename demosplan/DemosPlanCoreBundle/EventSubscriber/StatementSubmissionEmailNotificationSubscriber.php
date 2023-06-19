<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\GuestStatementSubmittedEvent;
use demosplan\DemosPlanCoreBundle\Event\MultipleStatementsSubmittedEvent;
use demosplan\DemosPlanCoreBundle\Logic\Statement\GdprConsentRevokeTokenService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementSubmissionNotifier;
use Exception;

class StatementSubmissionEmailNotificationSubscriber extends BaseEventSubscriber
{
    public function __construct(private readonly PermissionsInterface $permissions, private readonly StatementSubmissionNotifier $statementSubmissionNotifier, private readonly GdprConsentRevokeTokenService $gdprConsentRevokeTokenService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MultipleStatementsSubmittedEvent::class => 'notifyMultiple',
            GuestStatementSubmittedEvent::class     => 'notifySingle',
        ];
    }

    public function notifyMultiple(MultipleStatementsSubmittedEvent $event): void
    {
        if (0 < (is_countable($event->getSubmittedStatements()) ? count($event->getSubmittedStatements()) : 0)) {
            if (!$event->isPublic()) {
                // Use Statements instead of DraftStatements because ConsultationToken is required later,
                // which cant be found with information of the DraftStatements
                $this->statementSubmissionNotifier->sendConfirmationMails($event->getSubmittedStatements());
            }

            if ($this->permissions->hasPermission('feature_notification_statement_new')) {
                try {
                    // Benachrichtigung für Fachplaner
                    $this->statementSubmissionNotifier->sendNotificationEmailForAdmins($event->getSubmittedStatements());
                } catch (Exception $e) {
                    $this->getLogger()->warning('Get Sending Notification Email failed: ', [$e]);
                }
            }
        }
    }

    public function notifySingle(GuestStatementSubmittedEvent $event): void
    {
        if ($event->hasEmailAddress()) {
            $gdprConsentRevokeToken = null;
            if ($event->getSubmittedStatement() instanceof Statement) {
                $gdprConsentRevokeToken = $this->gdprConsentRevokeTokenService->maybeCreateGdprConsentRevokeToken(
                    $event->getEmailAddress(),
                    $event->getSubmittedStatement()->getOriginal()
                );
            }

            $this->statementSubmissionNotifier->sendEmailOnNewStatement(
                $event->getEmailText(),
                $event->getEmailAddress(),
                $event->getSubmittedStatement(),
                $event->getSubmittedStatement()->getExternId(),
                $gdprConsentRevokeToken,
            );
        }
    }
}
