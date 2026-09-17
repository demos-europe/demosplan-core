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
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StatementSegmentEventSubscriber extends BaseEventSubscriber
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SegmentRepository $segmentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateFlushEvent::class => [
                ['saveChangeHistory', 0],
                ['dispatchRecommendationSavedEvent', 0],
                ['dispatchAssignmentSavedEvent', 0],
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
     * The single-segment JSON:API update path calls {@see Segment::setRecommendation()} directly
     * (see the callback on the `recommendation` property in {@see StatementSegmentResourceType}),
     * so unlike a reflection-written property, the segment already holds the new value by the
     * time this event fires. The event itself does not say whether recommendation was one of the
     * updated fields, so the pre-edit value is read via {@see SegmentRepository::getOriginalEntityData()}
     * to detect an actual change.
     */
    public function dispatchRecommendationSavedEvent(BeforeResourceUpdateFlushEvent $event): void
    {
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getEntity();
        $originalData = $this->segmentRepository->getOriginalEntityData($segment);
        $originalRecommendation = $originalData['recommendation'] ?? null;

        if ($segment->getRecommendation() === $originalRecommendation) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new SegmentRecommendationsSavedEvent(
                [$segment],
                (string) $segment->getRecommendation(),
                false,
                [$segment->getId() => (string) $originalRecommendation],
                $this->currentUser->getUser()
            ),
            SegmentRecommendationsSavedEventInterface::class
        );
    }

    /**
     * The single-segment JSON:API update path leaves `assignee`/`place` on the default,
     * reflection-based `updatable()`, which bypasses any setter. By the time this event fires,
     * {@see DoctrineResourceType::updateEntity()} has already written the new value directly onto
     * the entity, so the segment only ever holds the new value here, never the old one. The
     * pre-edit value is read via {@see SegmentRepository::getOriginalEntityData()} instead.
     */
    public function dispatchAssignmentSavedEvent(BeforeResourceUpdateFlushEvent $event): void
    {
        $targetResourceType = $event->getType();
        if (!$targetResourceType instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getEntity();
        $originalData = $this->segmentRepository->getOriginalEntityData($segment);
        /** @var UserInterface|null $previousAssignee */
        $previousAssignee = $originalData['assignee'] ?? null;
        /** @var PlaceInterface|null $previousPlace */
        $previousPlace = $originalData['place'] ?? null;

        if ($segment->getAssignee() === $previousAssignee && $segment->getPlace() === $previousPlace) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new SegmentAssignmentOrPlaceChangeEvent(
                [$segment],
                [$segment->getId() => $previousAssignee],
                [$segment->getId() => $previousPlace]
            ),
            SegmentAssignmentOrPlaceChangeEventInterface::class
        );
    }
}
