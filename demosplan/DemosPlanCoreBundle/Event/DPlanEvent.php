<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class DPlanEvent extends Event
{
    /**
     * Get subject of the event to be dispatched.
     *
     * @return string
     */
    public function getSubject()
    {
        return static::class;
    }
}
