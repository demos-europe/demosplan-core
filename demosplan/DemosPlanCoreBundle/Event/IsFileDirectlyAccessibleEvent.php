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

use DemosEurope\DemosplanAddon\Contracts\Events\IsFileDirectlyAccessibleEventInterface;

class IsFileDirectlyAccessibleEvent extends DPlanEvent implements IsFileDirectlyAccessibleEventInterface
{
    /**
     * @var bool
     */
    private $isDirectlyAccessible = false;

    public function setIsDirectlyAccessible(bool $isDirectlyAccessible): void
    {
        $this->isDirectlyAccessible = $isDirectlyAccessible;
    }

    public function isFileDirectlyAccessible(): bool
    {
        return $this->isDirectlyAccessible;
    }
}
