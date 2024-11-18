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

use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceUpdateFlushEvent;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementUpdatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementUpdatedEvent;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use EDT\JsonApi\Event\AfterUpdateEvent;
use EDT\JsonApi\Event\BeforeUpdateEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StatementEventSubscriber extends BaseEventSubscriber
{
    private $changes = [];
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StatementRepository $statementRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateFlushEvent::class => 'saveChangeHistory',
            BeforeUpdateEvent::class => 'beforeUpdateEvent',
            AfterUpdateEvent::class => 'afterUpdateEvent',
        ];
    }

    public function beforeUpdateEvent(BeforeUpdateEvent $event): void
    {
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementResourceType) {
            return;
        }

        $statementId = $event->getEntityIdentifier();
        $currentStatementObject = $this->statementRepository->find($statementId);
        $this->changes[$statementId] = $currentStatementObject->getTextRaw();
//        $this->eventDispatcher->dispatch(
//            new StatementUpdatedEvent($currentStatementObject, $statementId),
//            StatementUpdatedEventInterface::class
//        );

    }
    public function afterUpdateEvent(AfterUpdateEvent $event): void
    {
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementResourceType) {
            return;
        }

        $statementTextRaw = $event->getEntity()->getTextRaw();
        $oldStatement = $this->changes[$event->getEntity()->getId()];
//        $this->eventDispatcher->dispatch(
//            new StatementUpdatedEvent($currentStatementObject, $statementId),
//            StatementUpdatedEventInterface::class
//        );

    }

    public function saveChangeHistory(BeforeResourceUpdateFlushEvent $event): void
    {
        return;
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementResourceType) {
            return;
        }

        /** @var Statement $statement */
        $statement = $event->getEntity();
        $currentStatementObject = $this->statementRepository->find($statement->getId());
        $this->eventDispatcher->dispatch(
            new StatementUpdatedEvent($currentStatementObject, $statement),
            StatementUpdatedEventInterface::class
        );
    }
}
