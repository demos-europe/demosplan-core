<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Events\GetDatasheetVersionEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class GetDatasheetFilePathAbsoluteEvent extends DPlanEvent implements GetDatasheetVersionEventInterface
{
    private string $datasheetFilePathAbsolute;

    public function setDatasheetFilePathAbsolute(string $datasheetFilePathAbsolute): void
    {
        $this->datasheetFilePathAbsolute = $datasheetFilePathAbsolute;
    }

    public function getDatasheetFilePathAbsolute(): string
    {
        return $this->datasheetFilePathAbsolute;
    }
}
