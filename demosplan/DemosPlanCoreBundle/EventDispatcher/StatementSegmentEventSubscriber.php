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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceUpdateFlushEvent;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;

class StatementSegmentEventSubscriber extends BaseEventSubscriber
{
    /**
     * @var EntityContentChangeService
     */
    private $entityContentChangeService;

    public function __construct(EntityContentChangeService $entityContentChangeService)
    {
        $this->entityContentChangeService = $entityContentChangeService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateFlushEvent::class => 'saveChangeHistory',
        ];
    }

    public function saveChangeHistory(BeforeResourceUpdateFlushEvent $event): void
    {
        $targetResourceType = $event->getResourceChange()->getTargetResourceType();
        if (!$targetResourceType instanceof StatementSegmentResourceType) {
            return;
        }

        /** @var Segment $segment */
        $segment = $event->getResourceChange()->getTargetResource();
        $this->entityContentChangeService->saveEntityChanges($segment, Segment::class);
    }
}
