<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

class IsFileAvailableEvent extends DPlanEvent
{
    /**
     * @param bool
     */
    private $isAvailable = false;

    public function setIsAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
    }

    public function isFileAvailable(): bool
    {
        return $this->isAvailable;
    }
}
