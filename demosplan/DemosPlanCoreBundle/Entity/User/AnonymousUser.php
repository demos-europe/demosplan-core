<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use RuntimeException;

class AnonymousUser extends FunctionalUser
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->id = self::ANONYMOUS_USER_ID;
        $this->login = self::ANONYMOUS_USER_LOGIN;
        $this->lastname = self::ANONYMOUS_USER_NAME;
        $this->functionalOrga = new Orga();
        $this->functionalOrga->setId(self::ANONYMOUS_USER_ORGA_ID);
        $this->functionalOrga->setName(self::ANONYMOUS_USER_ORGA_NAME);
        $this->department = new Department();
        $this->department->setId(self::ANONYMOUS_USER_DEPARTMENT_ID);
        $this->department->setName(self::ANONYMOUS_USER_DEPARTMENT_NAME);
        $this->functionalOrga->setDepartments([$this->department]);

        $role = new Role();
        $role->setCode(Role::GUEST);
        $role->setGroupCode(Role::GGUEST);

        $this->setDplanroles([$role]);

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function getDraftStatementSubmissionReminderEnabled(): bool
    {
        return false;
    }

    public function setDplanroles(
        array $roles,
        $customer = null
    ): void {
        if (null === $this->dplanRoles || 0 === $this->dplanRoles->count()) {
            parent::setDplanroles($roles, $customer);

            return;
        }

        throw new RuntimeException('Changing roles of anonymous user at runtime is disallowed');
    }
}
