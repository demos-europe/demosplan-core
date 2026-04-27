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

use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceUpdateFlushEvent;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\SegmentLockedException;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentLockEnforcementService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rejects JSON:API PATCH writes to a segment whose current workflow place has
 * `locked = true` when the segment lock feature is enabled via project config
 * and the caller lacks the segment-lock administration permission.
 * Wraps the EDT update pipeline via BeforeResourceUpdateFlushEvent.
 *
 * The check uses the *original* place (read from the UnitOfWork's original
 * entity data so that a non-admin cannot escape the lock by including a place
 * change in the PATCH payload.
 *
 * Holders of the administration permission are short-circuited inside the
 * enforcement service and pass through unaffected, enabling the FPA unlock
 * flow (move segment to an unlocked place + reassign).
 */
class SegmentLockEnforcementSubscriber extends BaseEventSubscriber
{
    public function __construct(
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBagInterface $messageBag,
        private readonly SegmentLockEnforcementService $segmentLockEnforcementService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 100 — runs before the change-history tracker so a rejected
        // write never produces an EntityContentChange row.
        return [
            BeforeResourceUpdateFlushEvent::class => ['enforceLock', 100],
        ];
    }

    public function enforceLock(BeforeResourceUpdateFlushEvent $event): void
    {
        if (!$event->getType() instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getEntity();

        $originalPlace = $this->resolveOriginalPlace($segment);

        if ($this->segmentLockEnforcementService->isPlaceLockedForCurrentUser($originalPlace)) {
            $this->messageBag->add('error', 'error.segment.locked.by.place');

            throw new SegmentLockedException(sprintf('Segment %s is locked for the current user.', $segment->getId() ?? '<unsaved>'));
        }

        // Enforcement passed (either segment was on an unlocked place or the
        // caller holds the administration permission). If the PATCH changes
        // the segment's place AND that crosses the lock/unlock boundary,
        // record a Versionsverlauf entry. The service gates itself on the
        // feature flag and skips when old and new lock state match.
        $this->entityContentChangeService->createSegmentLockedChangeEntryOnPlaceChange(
            $segment,
            $originalPlace,
            $segment->getPlace(),
        );
    }

    private function resolveOriginalPlace(Segment $segment): ?Place
    {
        // getOriginalEntityData returns the field values as loaded from the
        // database at the time the entity was hydrated, independent of
        // whether the Doctrine change set has been computed yet. Using the
        // change set here fails because BeforeResourceUpdateFlushEvent can
        // fire before computeChangeSets runs, which leaves the change set
        // empty and makes the fallback read the already-mutated place.
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($segment);
        if (isset($originalData['place']) && $originalData['place'] instanceof Place) {
            return $originalData['place'];
        }

        // Fallback for entities not tracked by the UoW yet (no original data recorded).
        return $segment->getPlace();
    }
}
