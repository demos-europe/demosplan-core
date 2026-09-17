<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Segment;

use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentAssignmentOrPlaceChangeEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class SegmentAssignmentOrPlaceChangeEvent extends DPlanEvent implements SegmentAssignmentOrPlaceChangeEventInterface
{
    /**
     * @param array<int, SegmentInterface>    $segments
     * @param array<string, ?UserInterface>   $previousAssignees keyed by segment id
     * @param array<string, ?PlaceInterface>  $previousPlaces    keyed by segment id
     */
    public function __construct(
        protected array $segments,
        protected array $previousAssignees,
        protected array $previousPlaces,
    ) {
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getPreviousAssignees(): array
    {
        return $this->previousAssignees;
    }

    public function getPreviousPlaces(): array
    {
        return $this->previousPlaces;
    }
}
