<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use Doctrine\Common\Collections\ArrayCollection;

class AiApiUser extends FunctionalUser
{
    public const AI_API_USER_LOGIN = 'aiapi+internal-users@demosplan';
    public const AI_API_USER_ID = '00000000-0000-0000-0000-000000000001';

    public function __construct(Customer $customer)
    {
        parent::__construct();

        $this->id = self::AI_API_USER_ID;
        $this->login = self::AI_API_USER_LOGIN;

        $this->functionalOrga = new Orga();
        $this->functionalOrga->setId(self::ANONYMOUS_USER_ORGA_ID);
        $this->functionalOrga->setName(self::ANONYMOUS_USER_ORGA_NAME);

        $this->department = new Department();
        $this->department->setId(self::ANONYMOUS_USER_DEPARTMENT_ID);
        $this->department->setName(self::ANONYMOUS_USER_DEPARTMENT_NAME);

        $role = new Role();
        $role->setCode(Role::API_AI_COMMUNICATOR);
        $role->setGroupCode(Role::GAICOM);

        $this->setDplanroles([$role]);

        $userRoleInCustomer = new UserRoleInCustomer();
        $userRoleInCustomer->setUser($this);
        $userRoleInCustomer->setRole($role);
        $userRoleInCustomer->setCustomer($customer);
        $this->roleInCustomers = new ArrayCollection([$userRoleInCustomer]);
    }
}
