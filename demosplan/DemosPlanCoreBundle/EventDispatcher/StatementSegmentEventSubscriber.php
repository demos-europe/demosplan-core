<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventDispatcher;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ResourceValidationSubscriber;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceUpdateFlushEvent;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentAssignmentOrPlaceChangeEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentRecommendationsSavedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\DoctrineResourceType;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentAssignmentOrPlaceChangeEvent;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentRecommendationsSavedEvent;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;

class StatementSegmentEventSubscriber extends BaseEventSubscriber
{
    /**
     * Must run before any listener that flushes, in particular before {@see saveChangeHistory()}:
     * a flush rewrites the unit of work's original entity data to the current values, after which
     * the pre-edit values this listener compares against are gone. Running before the addon's
     * {@see ResourceValidationSubscriber} is harmless, as the registered events are only dispatched
     * once the surrounding transaction has committed and are discarded when validation makes it roll back.
     */
    private const DECISION_LOG_PRIORITY = 10;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly SegmentRepository $segmentRepository,
        private readonly TransactionService $transactionService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateFlushEvent::class => [
                ['registerDecisionLogEvents', self::DECISION_LOG_PRIORITY],
                ['saveChangeHistory', 0],
            ],
        ];
    }

    public function saveChangeHistory(BeforeResourceUpdateFlushEvent $event): void
    {
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getEntity();
        $this->entityContentChangeService->trackChanges($segment, Segment::class);
    }

    /**
     * By the time this event fires, {@see DoctrineResourceType::updateEntity()} has already written
     * the new values onto the segment, either via {@see Segment::setRecommendation()} (see the callback
     * on the `recommendation` property in {@see StatementSegmentResourceType}) or via the default
     * reflection-based `updatable()` for `assignee` and `place`. The segment therefore only holds the
     * new values here. The event does not tell which fields were part of the update, so the pre-edit
     * values are read from the unit of work via {@see SegmentRepository::getOriginalEntityData()}
     * to detect actual changes.
     *
     * The events are registered with {@see TransactionService::dispatchAfterCommit()} and only reach
     * their listeners once the surrounding transaction has committed.
     */
    public function registerDecisionLogEvents(BeforeResourceUpdateFlushEvent $event): void
    {
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getEntity();
        $originalData = $this->segmentRepository->getOriginalEntityData($segment);
        if ([] === $originalData) {
            // without a persisted baseline in the unit of work no change can be told apart from a no-op
            return;
        }

        $this->registerRecommendationSavedEvent($segment, $originalData);
        $this->registerAssignmentOrPlaceChangeEvent($segment, $originalData);
    }

    /**
     * @param array<string, mixed> $originalData
     */
    private function registerRecommendationSavedEvent(Segment $segment, array $originalData): void
    {
        $originalRecommendation = (string) ($originalData['recommendation'] ?? '');
        if ($segment->getRecommendation() === $originalRecommendation) {
            return;
        }

        $this->transactionService->dispatchAfterCommit(
            new SegmentRecommendationsSavedEvent(
                [$segment],
                $segment->getRecommendation(),
                false,
                [$segment->getId() => $originalRecommendation],
                $this->currentUser->getUser()
            ),
            SegmentRecommendationsSavedEventInterface::class
        );
    }

    /**
     * @param array<string, mixed> $originalData
     */
    private function registerAssignmentOrPlaceChangeEvent(Segment $segment, array $originalData): void
    {
        /** @var UserInterface|null $previousAssignee */
        $previousAssignee = $originalData['assignee'] ?? null;
        /** @var PlaceInterface|null $previousPlace */
        $previousPlace = $originalData['place'] ?? null;

        if ($segment->getAssignee() === $previousAssignee
            && $segment->getPlace() === $previousPlace) {
            return;
        }

        $this->transactionService->dispatchAfterCommit(
            new SegmentAssignmentOrPlaceChangeEvent(
                [$segment],
                [$segment->getId() => $previousAssignee],
                [$segment->getId() => $previousPlace]
            ),
            SegmentAssignmentOrPlaceChangeEventInterface::class
        );
    }
}
