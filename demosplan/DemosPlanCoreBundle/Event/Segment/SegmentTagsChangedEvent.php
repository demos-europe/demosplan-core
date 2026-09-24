<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Segment;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentTagsChangedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class SegmentTagsChangedEvent extends DPlanEvent implements SegmentTagsChangedEventInterface
{
    /**
     * @param StatementInterface[] $statements
     */
    public function __construct(private readonly array $statements)
    {
    }

    public function getStatements(): array
    {
        return $this->statements;
    }
}
