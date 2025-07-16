<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use DemosEurope\DemosplanAddon\Contracts\Events\IsFileAvailableEventInterface;

class IsFileAvailableEvent extends DPlanEvent implements IsFileAvailableEventInterface
{
    /**
     * @var bool
     */
    private $isFileAvailable = false;

    public function setIsFileAvailable(bool $isFileAvailable): void
    {
        $this->isFileAvailable = $isFileAvailable;
    }

    public function isFileAvailable(): bool
    {
        return $this->isFileAvailable;
    }
}
