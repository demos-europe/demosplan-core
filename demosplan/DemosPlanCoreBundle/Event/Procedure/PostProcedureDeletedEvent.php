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

use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureDeletedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class PostProcedureDeletedEvent extends DPlanEvent implements PostProcedureDeletedEventInterface
{
    /** @var array<string, mixed> */
    protected $procedureData;

    /**
     * @param array<string, mixed> $procedureData
     */
    public function __construct(array $procedureData)
    {
        $this->procedureData = $procedureData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProcedureData(): array
    {
        return $this->procedureData;
    }
}
