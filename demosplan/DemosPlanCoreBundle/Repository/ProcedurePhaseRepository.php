<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Repositories\ProcedurePhaseRepositoryInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;

class ProcedurePhaseRepository extends FluentRepository implements ProcedurePhaseRepositoryInterface
{
    public function getProcedureByInstitutionPhaseId(string $phaseId): ?ProcedureInterface
    {
        return $this->getEntityManager()->getRepository(ProcedureInterface::class)->findOneBy(['phase' => $phaseId]);
    }

    public function getProcedureByPublicParticipationPhaseId(string $phaseId): ?ProcedureInterface
    {
        return $this->getEntityManager()->getRepository(ProcedureInterface::class)
            ->findOneBy(['publicParticipationPhase' => $phaseId]);
    }
}
