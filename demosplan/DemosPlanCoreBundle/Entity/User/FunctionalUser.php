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
use Doctrine\Common\Collections\Collection;

/**
 * A user which is purely functional and does not exist in the database.
 */
class FunctionalUser extends User
{
    public const FUNCTIONAL_USER_CUSTOMER_SUBDOMAIN = 'any';
    private const FUNCTIONAL_USER_CUSTOMER_NAME = 'any';

    /**
     * Organisation of the functional user.
     *
     * @var Orga
     */
    protected $functionalOrga;

    /**
     * Roles of anonymous user (usually just GUEST).
     *
     * @var Collection
     */
    protected $dplanRoles;

    public function __construct()
    {
        // user just needs some current customer, it does not care which
        // as "calculating", which role user has is hard coded
        $this->currentCustomer = new Customer(
            self::FUNCTIONAL_USER_CUSTOMER_NAME,
            self::FUNCTIONAL_USER_CUSTOMER_SUBDOMAIN
        );

        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function isNewUser(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isProfileCompleted(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoleBySubdomain(string $subdomain): string
    {
        return Role::GUEST;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrga(): Orga
    {
        return $this->functionalOrga;
    }

    /**
     * Has to be overridden to ignore the customer.
     */
    public function getDplanroles(Customer $customer = null): Collection
    {
        return $this->dplanRoles;
    }

    /**
     * Functional users have the connection to the roles DB severed,
     * roles are managed programmatically.
     *
     * @param null $customer
     */
    public function setDplanroles(
        array $roles,
        $customer = null
    ): void {
        $this->dplanRoles = new ArrayCollection();

        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $this->dplanRoles->add($role);
            }
        }
    }
}
