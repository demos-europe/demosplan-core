<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links the user, the role and the customer (currently only relevant for the CustomerMasterUser).
 *
 * @see for Details https://yaits.demos-deutschland.de/w/demosplan/functions/user_roles/
 *
 * @ORM\Table(name="relation_role_user_customer",
 *    uniqueConstraints={
 *
 *        @ORM\UniqueConstraint(name="role_customer_user_unique_constraint",
 *            columns={"role", "customer", "user"})
 *    }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\UserRoleInCustomerRepository")
 */
class UserRoleInCustomer extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Foreign key, User object.
     *
     * @var User
     *
     * @ORM\ManyToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User",
     *     inversedBy="roleInCustomers",
     * )
     *
     * @ORM\JoinColumn(name="user", referencedColumnName="_u_id", nullable=false)
     */
    protected $user;

    /**
     * Foreign key, Role object.
     *
     * @var Role
     *
     * @ORM\ManyToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role",
     *     inversedBy="userRoleInCustomers"
     * )
     *
     * @ORM\JoinColumn(name="role", referencedColumnName="_r_id", nullable=false)
     */
    protected $role;

    /**
     * Foreign key, Customer object.
     *
     * @var Customer|null
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/
     *
     * @ORM\ManyToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer",
     *     inversedBy="userRoles"
     * )
     *
     * @ORM\JoinColumn(name="customer", referencedColumnName="_c_id", nullable=true)
     */
    protected $customer;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set User.
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get User.
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set Role.
     */
    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Get Role.
     */
    public function getRole(): Role
    {
        return $this->role;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;
        if (null !== $customer && !$customer->getUserRoles()->contains($this)) {
            $customer->getUserRoles()->add($this);
        }

        return $this;
    }

    /**
     * Get Customer.
     *
     * @return Customer|null
     */
    public function getCustomer()
    {
        return $this->customer;
    }
}
