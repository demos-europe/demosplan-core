<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;

/**
 * Principal for a request authenticated by a procedure integration token.
 *
 * Its own identity rather than {@see AiApiUser}, so a pushed recommendation is attributable. It does
 * share {@see RoleInterface::API_AI_COMMUNICATOR}, since a dedicated role code would have to be added
 * to the demosplan-addon package; the security boundary is the token's scope, not the role.
 */
class ProcedureIntegrationUser extends FunctionalUser
{
    final public const PROCEDURE_INTEGRATION_USER_LOGIN = 'integration+internal-users@demosplan';
    final public const PROCEDURE_INTEGRATION_USER_ID = '00000000-0000-0000-0000-000000000002';

    public function __construct()
    {
        $this->id = self::PROCEDURE_INTEGRATION_USER_ID;
        $this->login = self::PROCEDURE_INTEGRATION_USER_LOGIN;

        $role = new Role();
        $role->setCode(RoleInterface::API_AI_COMMUNICATOR);
        $role->setGroupCode(RoleInterface::GAICOM);

        $this->setDplanroles([$role]);

        parent::__construct();
    }
}
