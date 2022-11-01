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
