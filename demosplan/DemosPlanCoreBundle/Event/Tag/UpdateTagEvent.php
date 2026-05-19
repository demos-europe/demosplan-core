<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Tag;

use DemosEurope\DemosplanAddon\Contracts\Events\UpdateTagEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class UpdateTagEvent extends DPlanEvent implements UpdateTagEventInterface
{
    public function __construct(private readonly string $tagId)
    {
    }

    public function getTagId(): string
    {
        return $this->tagId;
    }
}
