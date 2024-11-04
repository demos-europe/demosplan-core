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

        $role = new Role();
        $role->setCode(RoleInterface::GUEST);
        $role->setGroupCode(RoleInterface::GGUEST);

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
