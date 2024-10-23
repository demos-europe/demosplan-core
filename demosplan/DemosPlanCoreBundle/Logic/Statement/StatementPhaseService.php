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
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Exception\UndefinedPhaseException;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
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

    /**
     * @throws UndefinedPhaseException
     */
    public function getPhaseVO(string $phaseKey, string $publicStatement): PhaseVO
    {
        $availablePhases = $this->getAvailablePhases($publicStatement);

        foreach ($availablePhases as $phase) {
            if ($phase->getKey() === $phaseKey) {
                // Phase key matches the name of the phase
                return $phase;
            }
        }
        throw new UndefinedPhaseException($phaseKey);
    }

    public function getAvailablePhases(string $publicStatement): array
    {
        // If the publicstatement is internal, then return only the internal phases
        // if the publicstatement is external, then return only the external phases

        if (StatementInterface::EXTERNAL === $publicStatement) {
            foreach ($this->globalConfig->getExternalPhasesAssoc() as $internalPhase) {
                $phases[] = $this->createPhaseVO($internalPhase, Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL);
            }

            return $phases;
        }

        if (StatementInterface::INTERNAL === $publicStatement) {
            foreach ($this->globalConfig->getInternalPhasesAssoc() as $internalPhase) {
                $phases[] = $this->createPhaseVO($internalPhase, Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL);
            }

            return $phases;
        }

        return [];
    }
}
