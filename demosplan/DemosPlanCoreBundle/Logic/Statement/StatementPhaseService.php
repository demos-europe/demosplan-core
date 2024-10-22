<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PhaseVO;

class StatementPhaseService
{
    public function __construct(private readonly GlobalConfigInterface $globalConfig)
    {
    }

    public function createPhaseVO(array $phase, string $type)
    {
        $phaseVO = new PhaseVO();
        $phaseVO->setKey($phase[PhaseVO::PROCEDURE_PHASE_KEY]);
        $phaseVO->setName($phase[PhaseVO::PROCEDURE_PHASE_NAME]);
        $phaseVO->setPermissionsSet($phase[PhaseVO::PROCEDURE_PHASE_PERMISSIONS_SET]);
        $phaseVO->setParticipationState($phase[PhaseVO::PROCEDURE_PHASE_PARTICIPATION_STATE] ?? null);
        $phaseVO->setPhaseType($type);

        return $phaseVO->lock();
    }
}
