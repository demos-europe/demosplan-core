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
    public function __construct(
        protected StatementStatistic $globalStatementStatistic,
        protected array $allowedRoleCodeMap,
        protected array $externalPhases,
        protected array $internalPhases,
        protected array $orgas,
        protected array $procedures,
        protected array $roles,
        protected array $usersPerOrga,
    ) {
        $this->lock();
    }

    public function getAsTemplateVars(): array
    {
        $templateVars = [];
        $templateVars['procedureList'] = $this->procedures;
        $templateVars['statementStatistic'] = $this->globalStatementStatistic;
        $templateVars['internalPhases'] = $this->internalPhases;
        $templateVars['externalPhases'] = $this->externalPhases;
        $templateVars['rolesList'] = $this->roles;
        $templateVars['orgaList'] = $this->orgas;
        $templateVars['orgaUsersList'] = $this->usersPerOrga;
        $templateVars['allowedRoleCodeMap'] = $this->allowedRoleCodeMap;

        return $templateVars;
    }
}
