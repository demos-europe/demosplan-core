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

class StatementSegmentEventSubscriber extends BaseEventSubscriber
{
    public function __construct(private readonly EntityContentChangeService $entityContentChangeService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateFlushEvent::class => 'saveChangeHistory',
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
        $this->entityContentChangeService->saveEntityChanges($segment, Segment::class);
    }
}
