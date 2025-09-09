<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use DemosEurope\DemosplanAddon\Contracts\Events\GetDatasheetVersionEventInterface;
use demosplan\DemosPlanCoreBundle\Event\Procedure\GetDatasheetVersionEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DatasheetService
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getDatasheetVersion(string $procedureId)
    {
        /** @var GetDatasheetVersionEvent $event * */
        $event = $this->eventDispatcher->dispatch(
            new GetDatasheetVersionEvent($procedureId),
            GetDatasheetVersionEventInterface::class
        );

        return $event->getDatasheetVersion();
    }
}
