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
use demosplan\DemosPlanCoreBundle\Exception\UndefinedPhaseException;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\ProcedurePhaseVO;

class StatementProcedurePhaseResolver
{
    public function __construct(private readonly GlobalConfigInterface $globalConfig)
    {
    }

    public function createProcedurePhaseVO(array $phase, string $type)
    {
        $phaseVO = new ProcedurePhaseVO();
        $phaseVO->setKey($phase[ProcedurePhaseVO::PROCEDURE_PHASE_KEY]);
        $phaseVO->setName($phase[ProcedurePhaseVO::PROCEDURE_PHASE_NAME]);
        $phaseVO->setPermissionsSet($phase[ProcedurePhaseVO::PROCEDURE_PHASE_PERMISSIONS_SET]);
        $phaseVO->setParticipationState($phase[ProcedurePhaseVO::PROCEDURE_PHASE_PARTICIPATION_STATE] ?? null);
        $phaseVO->setPhaseType($type);

        return $phaseVO->lock();
    }

    /**
     * @throws UndefinedPhaseException
     */
    public function getProcedurePhaseVO(string $phaseKey, bool $isSubmittedByCitizen): ProcedurePhaseVO
    {
        $availablePhases = $this->getAvailableProcedurePhases($isSubmittedByCitizen);

        foreach ($availablePhases as $phase) {
            if ($phase->getKey() === $phaseKey) {
                // Phase key matches the name of the phase
                return $phase;
            }
        }
        throw new UndefinedPhaseException($phaseKey);
    }

    public function getAvailableProcedurePhases(bool $isSubmittedByCitizen): array
    {
        $phases = [];

        if ($isSubmittedByCitizen) {
            foreach ($this->globalConfig->getExternalPhasesAssoc() as $internalPhase) {
                $phases[] = $this->createProcedurePhaseVO($internalPhase, Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL);
            }

            return $phases;
        }

        foreach ($this->globalConfig->getInternalPhasesAssoc() as $internalPhase) {
            $phases[] = $this->createProcedurePhaseVO($internalPhase, Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL);
        }

        return $phases;
    }
}
