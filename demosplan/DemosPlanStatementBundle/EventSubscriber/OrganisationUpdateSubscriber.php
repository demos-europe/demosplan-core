<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Event\User\OrgaEditedEvent;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanStatementBundle\Logic\DraftStatementService;

class OrganisationUpdateSubscriber extends BaseEventSubscriber
{
    /**
     * @var DraftStatementService
     */
    protected $draftStatementService;

    public function __construct(DraftStatementService $draftStatementService)
    {
        $this->draftStatementService = $draftStatementService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrgaEditedEvent::class => 'onEditOrgaAction',
        ];
    }

    public function onEditOrgaAction(OrgaEditedEvent $event): void
    {
        $orgaBefore = $event->getOrganisationBefore();
        $orgaUpdated = $event->getOrganisationUpdated();

        // new submissiontype is short
        if ($orgaBefore->getSubmissionType() != $orgaUpdated->getSubmissionType()
            && Orga::STATEMENT_SUBMISSION_TYPE_SHORT == $orgaUpdated->getSubmissionType()) {
            $success = $this->draftStatementService->resetDraftStatementsOfProceduresOfOrga($event->getOrganisationUpdated());
            if ($success) {
                MessageBag::addMessage('confirm', 'confirm.statement.orgaedit.submissiontype.short');
            } else {
                MessageBag::addMessage('error', 'error.statement.orgaedit.submissiontype.short');
            }
        }

        // new submissiontype is default
        if ($orgaBefore->getSubmissionType() != $orgaUpdated->getSubmissionType()
            && Orga::STATEMENT_SUBMISSION_TYPE_DEFAULT == $orgaUpdated->getSubmissionType()) {
            // Nothing to do but tell user that resetting worked
            MessageBag::addMessage('confirm', 'confirm.statement.orgaedit.submissiontype.default');
        }
    }
}
