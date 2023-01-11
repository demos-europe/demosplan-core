<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\Logic\Statement\NonAuthorizedAssignRemover;
use demosplan\DemosPlanCoreBundle\Logic\Statement\NonAuthorizedCaseworkerRemover;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NonAuthorizedAssignRemoveSubscriber implements EventSubscriberInterface
{
    private NonAuthorizedAssignRemover $assignRemover;
    private NonAuthorizedCaseworkerRemover $caseworkerRemover;

    public function __construct(NonAuthorizedAssignRemover $assignRemover, NonAuthorizedCaseworkerRemover $caseworkerRemover)
    {
        $this->assignRemover = $assignRemover;
        $this->caseworkerRemover = $caseworkerRemover;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProcedureEditedEvent::class => 'removeUnauthorizedProcedureAssignments',
        ];
    }

    public function removeUnauthorizedProcedureAssignments(ProcedureEditedEvent $event): void
    {
        $procedureId = $event->getProcedureId();
        // Find and remove unauthorized assignees
        $this->assignRemover->removeNonAuthorizedAssignees($procedureId);
        // Find and remove unauthorized caseworkers
        $this->caseworkerRemover->removeNonAuthorizedCaseWorkers($procedureId);
    }
}
