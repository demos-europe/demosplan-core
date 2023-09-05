<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\Logic\OriginalStatementAnonymizationService;
use Exception;

class OriginalStatementAnonymizationCreateSubscriber extends BaseEventSubscriber
{
    public function __construct(private readonly OriginalStatementAnonymizationService $statementAnonymizationService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StatementAnonymizeRpcEvent::class => 'recordAnonymization',
        ];
    }

    public function recordAnonymization(StatementAnonymizeRpcEvent $event): void
    {
        try {
            $entity = $this->statementAnonymizationService->createFromParameters(
                $event->getCurrentUser()->getUser(),
                $event->getStatement(),
                $event->isDeleteStatementAttachments(),
                $event->isAnonymizeStatementText(),
                $event->isDeleteStatementTextHistory(),
                $event->isAnonymizeStatementMeta()
            );
            $this->statementAnonymizationService->persist($entity);
        } catch (Exception $e) {
            $event->setException($e);
        }
    }
}
