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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;

class ProcedurePhaseService
{
    /**
     * Check whether procedure is currently in a public consultation phase.
     */
    public function isPublicConsultationPhase(Procedure $procedure): bool
    {
        return Procedure::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN
            === $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getParticipationState();
    }
}
