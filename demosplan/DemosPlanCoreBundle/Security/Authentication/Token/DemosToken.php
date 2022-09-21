<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Token;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DemosToken extends AbstractToken implements TokenInterface
{
    /**
     * {@inheritdoc}
     */
    public function __construct(User $user, Customer $customer = null)
    {
        parent::__construct($user->getDplanRolesArray($customer));

        $this->setUser($user);

        // If the user is not a guest, consider it authenticated
        $this->setAuthenticated(!$user->hasRole(Role::GUEST, $customer));
    }

    /**
     * @return string
     */
    public function getCredentials()
    {
        return '';
    }
}
