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
    /**
     * @var array<int, list<ProcedurePhaseVO>>
     */
    private array $cachedPhases = [];

    /**
     * @var array<int, array<string, ProcedurePhaseVO>>
     */
    private array $cachedPhaseMap = [];

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
        $phaseMap = $this->getPhaseMap($isSubmittedByCitizen);
        if (isset($phaseMap[$phaseKey])) {
            return $phaseMap[$phaseKey];
        }

        throw new UndefinedPhaseException($phaseKey);
    }

    /**
     * @return list<ProcedurePhaseVO>
     */
    public function getAvailableProcedurePhases(bool $isSubmittedByCitizen): array
    {
        $bucket = (int) $isSubmittedByCitizen;
        if (!isset($this->cachedPhases[$bucket])) {
            $this->cachedPhases[$bucket] = $this->buildAvailableProcedurePhases($isSubmittedByCitizen);
        }

        return $this->cachedPhases[$bucket];
    }

    /**
     * @return list<ProcedurePhaseVO>
     */
    private function buildAvailableProcedurePhases(bool $isSubmittedByCitizen): array
    {
        $source = $isSubmittedByCitizen
            ? $this->globalConfig->getExternalPhasesAssoc()
            : $this->globalConfig->getInternalPhasesAssoc();
        $scope = $isSubmittedByCitizen
            ? Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL
            : Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL;

        $phases = [];
        foreach ($source as $phaseConfig) {
            $phases[] = $this->createProcedurePhaseVO($phaseConfig, $scope);
        }

        return $phases;
    }

    /**
     * @return array<string, ProcedurePhaseVO>
     */
    private function getPhaseMap(bool $isSubmittedByCitizen): array
    {
        $bucket = (int) $isSubmittedByCitizen;
        if (!isset($this->cachedPhaseMap[$bucket])) {
            $map = [];
            foreach ($this->getAvailableProcedurePhases($isSubmittedByCitizen) as $phase) {
                $map[$phase->getKey()] = $phase;
            }
            $this->cachedPhaseMap[$bucket] = $map;
        }

        return $this->cachedPhaseMap[$bucket];
    }
}
