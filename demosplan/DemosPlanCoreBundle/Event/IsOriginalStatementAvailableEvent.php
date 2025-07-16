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

use DemosEurope\DemosplanAddon\Contracts\Events\IsOriginalStatementAvailableEventInterface;

class IsOriginalStatementAvailableEvent extends DPlanEvent implements IsOriginalStatementAvailableEventInterface
{
    /**
     * @var bool
     */
    private $isOriginalStatementAvailable = false;

    public function setIsOriginalStatementAvailable(bool $isOriginalStatementAvailable): void
    {
        $this->isOriginalStatementAvailable = $isOriginalStatementAvailable;
    }

    public function isOriginalStatementeAvailable(): bool
    {
        return $this->isOriginalStatementAvailable;
    }
}
