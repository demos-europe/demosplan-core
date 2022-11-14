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

use demosplan\DemosPlanCoreBundle\Event\ImportingStatementViaEmailEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement\StatementFromEmailCreator;

class EmailImportSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportingStatementViaEmailEvent::class => 'importingStatementViaEmail',
        ];
    }
    public function importingStatementViaEmail (ImportingStatementViaEmailEvent $event, StatementFromEmailCreator $emailStatementCreator)
    {
        $request = $event->getRequest();
        $procedureId = $event->getProcedureId();
        if ($emailStatementCreator->isImportingStatementViaEmail($request)) {
            return $emailStatementCreator($request, $procedureId);
        }
    }
}
