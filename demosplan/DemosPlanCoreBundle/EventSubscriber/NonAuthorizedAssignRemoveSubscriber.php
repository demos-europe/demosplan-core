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

use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\Logic\Statement\NonAuthorizedAssignRemover;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NonAuthorizedAssignRemoveSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly NonAuthorizedAssignRemover $assignRemover)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProcedureEditedEvent::class => 'removeNonAuthorizedAssignees',
        ];
    }

    public function removeNonAuthorizedAssignees(ProcedureEditedEvent $event): void
    {
        $this->assignRemover->removeNonAuthorizedAssignees($event->getProcedureId());
    }
}
