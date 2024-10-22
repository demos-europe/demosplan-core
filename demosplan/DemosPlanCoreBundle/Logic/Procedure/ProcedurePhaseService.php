<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PhaseVO;

class ProcedurePhaseService
{
    public function __construct(private readonly GlobalConfigInterface $globalConfig)
    {
    }

    /**
     * Check whether procedure is currently in a public consultation phase.
     */
    public function isPublicConsultationPhase(Procedure $procedure): bool
    {
        $externalPhases = collect($this->globalConfig->getExternalPhases());
        $currentPhase = $externalPhases->where('key', $procedure->getPublicParticipationPhase());
        if (1 === $currentPhase->count()) {
            $phase = $currentPhase->first();

            return array_key_exists(Procedure::PARTICIPATIONSTATE_KEY, $phase)
                && Procedure::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN === $phase[Procedure::PARTICIPATIONSTATE_KEY];
        }

        return false;
    }
}
