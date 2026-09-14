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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;

/**
 * Records a Versionsverlauf entry when a segment's workflow place change
 * triggers the automatic reset of its deadline (Bearbeitungsfrist).
 *
 * The reset itself is performed during flush by
 * {@see \demosplan\DemosPlanCoreBundle\EventListener\DoctrineSegmentListener}.
 * That happens after the generic change tracker
 * ({@see StatementSegmentEventSubscriber::saveChangeHistory}) has already run,
 * so the reset would otherwise never appear in the version history. This
 * subscriber records it explicitly, mirroring {@see SegmentLockEnforcementSubscriber}
 * for the segment-lock side effect.
 */
class SegmentDeadlineResetSubscriber extends BaseEventSubscriber
{
    public function __construct(
        private readonly EntityContentChangeService $entityContentChangeService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateFlushEvent::class => 'recordDeadlineReset',
        ];
    }

    public function recordDeadlineReset(BeforeResourceUpdateFlushEvent $event): void
    {
        if (!$event->getType() instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getEntity();
        $this->entityContentChangeService->createDeadlineResetChangeEntryOnPlaceChange($segment);
    }
}
