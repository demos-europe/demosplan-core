<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Tag;

use DemosEurope\DemosplanAddon\Contracts\Events\DeleteTagEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class DeleteTagEvent extends DPlanEvent implements DeleteTagEventInterface
{
    private bool $handledSuccessfully = false;

    public function __construct(private string $tagId)
    {
    }

    public function getTagId(): string
    {
        return $this->tagId;
    }

    public function hasBeenHandledSuccessfully(): bool
    {
        return $this->handledSuccessfully;
    }

    public function setHandledSuccessfully(bool $handledSuccessfully): void
    {
        $this->handledSuccessfully = $handledSuccessfully;
    }
}
