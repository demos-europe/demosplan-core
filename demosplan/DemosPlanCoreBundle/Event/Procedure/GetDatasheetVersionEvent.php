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

class GetDatasheetVersionEvent implements GetDatasheetVersionEventInterface
{
    private int $datasheetVersion = 0;

    public function __construct(private readonly string $procedureId)
    {
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setDatasheetVersion(int $datasheetVersion)
    {
        $this->datasheetVersion = $datasheetVersion;
    }

    public function getDatasheetVersion(): int
    {
        return $this->datasheetVersion;
    }
}
