<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementStatistic;

/**
 * @method array              getProcedures()
 * @method array              getInternalPhases()
 * @method array              getExternalPhases()
 * @method array              getRoles()
 * @method array              getOrgas()
 * @method array              getUsersPerOrga()
 * @method array              getAllowedRoleCodeMap()
 * @method StatementStatistic getGlobalStatementStatistic()
 */
class Statistics extends ValueObject
{
    protected array $procedures;
    protected array $internalPhases;
    protected array $externalPhases;
    protected array $roles;
    protected array $orgas;
    protected array $usersPerOrga;
    protected array $allowedRoleCodeMap;
    protected StatementStatistic $globalStatementStatistic;

    public function __construct(
        StatementStatistic $globalStatementStatistic,
        array $allowedRoleCodeMap,
        array $externalPhases,
        array $internalPhases,
        array $orgas,
        array $procedures,
        array $roles,
        array $usersPerOrga,
    ) {
        $this->procedures = $procedures;
        $this->internalPhases = $internalPhases;
        $this->externalPhases = $externalPhases;
        $this->roles = $roles;
        $this->orgas = $orgas;
        $this->usersPerOrga = $usersPerOrga;
        $this->allowedRoleCodeMap = $allowedRoleCodeMap;
        $this->globalStatementStatistic = $globalStatementStatistic;

        $this->lock();
    }

    public function getAsTemplateVars(): array
    {
        return ['procedureList' => $this->procedures, 'statementStatistic' => $this->globalStatementStatistic, 'internalPhases' => $this->internalPhases, 'externalPhases' => $this->externalPhases, 'rolesList' => $this->roles, 'orgaList' => $this->orgas, 'orgaUsersList' => $this->usersPerOrga, 'allowedRoleCodeMap' => $this->allowedRoleCodeMap];
    }
}
