<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Segment;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentRecommendationsSavedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class SegmentRecommendationsSavedEvent extends DPlanEvent implements SegmentRecommendationsSavedEventInterface
{
    /**
     * @param array<int, SegmentInterface> $segments
     * @param array<string, string>        $previousRecommendationTexts keyed by segment id
     */
    public function __construct(
        protected array $segments,
        protected string $recommendationText,
        protected bool $attached,
        protected array $previousRecommendationTexts,
        protected UserInterface $user,
    ) {
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getRecommendationText(): string
    {
        return $this->recommendationText;
    }

    public function isAttached(): bool
    {
        return $this->attached;
    }

    public function getPreviousRecommendationTexts(): array
    {
        return $this->previousRecommendationTexts;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
