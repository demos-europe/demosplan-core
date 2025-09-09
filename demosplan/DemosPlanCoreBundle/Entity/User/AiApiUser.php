<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;

class AiApiUser extends FunctionalUser
{
    final public const AI_API_USER_LOGIN = 'aiapi+internal-users@demosplan';
    final public const AI_API_USER_ID = '00000000-0000-0000-0000-000000000001';

    public function __construct()
    {
        $this->id = self::AI_API_USER_ID;
        $this->login = self::AI_API_USER_LOGIN;

        $role = new Role();
        $role->setCode(RoleInterface::API_AI_COMMUNICATOR);
        $role->setGroupCode(RoleInterface::GAICOM);

        $this->setDplanroles([$role]);

        parent::__construct();
    }
}
