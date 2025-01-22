<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Platform\Statistics;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementStatistic;
use demosplan\DemosPlanCoreBundle\ValueObject\Statistics;
use Exception;

class StatisticsGenerator
{
    private const ROLES_EXCLUDED_IN_EXPORT = [
        RoleInterface::API_AI_COMMUNICATOR,
        RoleInterface::GUEST,
        RoleInterface::PROSPECT,
        RoleInterface::CITIZEN,
    ];

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly OrgaService $orgaService,
        private readonly ProcedureService $procedureService,
        private readonly StatementService $statementService,
        private readonly UserService $userService,
    ) {
    }

    /**
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    public function generateStatistics(array $allowedRoles): Statistics
    {
        $procedureList = $this->procedureService->getProcedureFullList();
        $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
        $externalPhases = $this->globalConfig->getExternalPhasesAssoc();
        $originalStatements = $this->statementService->getOriginalStatements();
        $amountOfProcedures = $this->procedureService->getAmountOfProcedures();
        $globalStatementStatistic = new StatementStatistic($originalStatements, $amountOfProcedures);

        $modifiedResults = [];
        if ($procedureList['total'] > 0) {
            foreach ($procedureList['result'] as $procedureData) {
                $procedureData = $this->prepareProcedureData($procedureData, $globalStatementStatistic);
                $modifiedResults[$procedureData['id']] = $procedureData; // store modified data
                $internalPhases = $this->cacheProcedurePhase($procedureData, $internalPhases, 'phase');
                $externalPhases = $this->cacheProcedurePhase($procedureData, $externalPhases, 'publicParticipationPhase');
            }
            $procedureList['result'] = $modifiedResults; // actually overwrite data
        }

        return new Statistics(
            $procedureList['result'],
            $internalPhases,
            $externalPhases,
            $this->userService->collectRoleStatistics($this->userService->getUndeletedUsers()),
            $this->orgaService->getOrgaCountByTypeTranslated($this->customerService->getCurrentCustomer()),
            $this->userService->getOrgaUsersList(),
            $this->getAllowedRoleCodeMap($allowedRoles),
            $globalStatementStatistic
        );
    }

    private function prepareProcedureData(
        array $procedureData,
        StatementStatistic $globalStatementStatistic
    ): array {
        $procedureData['phaseName'] = $this->globalConfig->getPhaseNameWithPriorityInternal($procedureData['phase']);
        $procedureData['publicParticipationPhaseName'] = $this->globalConfig->getPhaseNameWithPriorityExternal($procedureData['publicParticipationPhase']);
        $procedureData['statementStatistic'] = $globalStatementStatistic->getStatisticDataForProcedure($procedureData['id']);

        return $procedureData;
    }

    private function cacheProcedurePhase(array $procedureData, array $procedurePhases, string $phaseType): array
    {
        if (0 < strlen($procedureData[$phaseType])) {
            isset($procedurePhases[$procedureData[$phaseType]]['num'])
                ? $procedurePhases[$procedureData[$phaseType]]['num']++
                : $procedurePhases[$procedureData[$phaseType]]['num'] = 1;
        }

        return $procedurePhases;
    }

    private function getAllowedRoleCodeMap(array $allowedRoles): array
    {
        $allowedRoleCodeMap = [];
        foreach ($allowedRoles as $allowedRoleCode) {
            if (!in_array($allowedRoleCode, self::ROLES_EXCLUDED_IN_EXPORT, true)) {
                $allowedRoleCodeMap[$allowedRoleCode] = RoleInterface::ROLE_CODE_NAME_MAP[$allowedRoleCode];
            }
        }
        return $allowedRoleCodeMap;
    }
}
