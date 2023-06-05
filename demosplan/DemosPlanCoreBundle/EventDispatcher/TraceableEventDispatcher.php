<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventDispatcher;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use Exception;
use Symfony\Contracts\EventDispatcher\Event;

class TraceableEventDispatcher extends \Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher implements EventDispatcherPostInterface
{
    /**
     * Dispatches an event with passing the arguments to a GenericEvent if no
     * custom Event is passed.
     *
     * @return Event
     *
     * @throws Exception
     */
    public function post(DPlanEvent $event)
    {
        return $this->dispatch($event, $event->getSubject());
    }
}
