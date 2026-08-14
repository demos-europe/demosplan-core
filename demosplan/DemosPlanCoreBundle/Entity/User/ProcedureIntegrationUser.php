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
 * Its own identity rather than a reuse of {@see AiApiUser}, so that a recommendation written from
 * another instance is attributable in report entries and recommendation versions instead of being
 * filed under the AI integration.
 *
 * It does share {@see RoleInterface::API_AI_COMMUNICATOR}, because a dedicated role code would have
 * to be added to the demosplan-addon package. The role is therefore not the security boundary here:
 * the token's scope is, enforced deny-by-default by
 * {@see \demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext},
 * and the push endpoint additionally refuses to act without an integration token context — so the
 * permission this role gains cannot be exercised through the AI integration's JWT path.
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
