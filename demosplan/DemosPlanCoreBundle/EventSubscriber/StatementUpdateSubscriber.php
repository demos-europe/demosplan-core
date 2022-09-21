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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\plugins\workflow\SegmentsManager\Logic\Segment\PiSegmentRecognitionRequester;

class StatementUpdateSubscriber extends BaseEventSubscriber
{
    /**
     * @var PiSegmentRecognitionRequester
     */
    private $piSegmentRecognitionRequester;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(
        PiSegmentRecognitionRequester $piSegmentRecognitionRequester,
        PermissionsInterface $permissions
    ) {
        $this->piSegmentRecognitionRequester = $piSegmentRecognitionRequester;
        $this->permissions = $permissions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterResourceUpdateEvent::class => 'generateDraftSegments',
        ];
    }

    public function generateDraftSegments(AfterResourceUpdateEvent $event): void
    {
        if ($this->permissions->hasPermission('feature_ai_generated_draft_segments')
            && array_key_exists('fullText', $event->getResourceChange()->getRequestProperties())) {
            /** @var Statement $statement */
            $statement = $event->getResourceChange()->getTargetResource();
            $this->piSegmentRecognitionRequester->request($statement);
        }
    }
}
