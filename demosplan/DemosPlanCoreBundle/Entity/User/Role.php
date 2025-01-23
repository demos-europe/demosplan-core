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
use DemosEurope\DemosplanAddon\Contracts\Entities\UserRoleInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

/**
 * @see for Details https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/user_roles/
 *
 * @ORM\Table(name="_role")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\RoleRepository")
 */
class Role extends CoreEntity implements UuidEntityInterface, RoleInterface, Stringable
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_r_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;
    /**
     * @var string
     *
     * @ORM\Column(name="_r_code", type="string", length=6, nullable=false, options={"fixed":true})
     */
    protected $code;
    /**
     * This property is set by {@link RoleEntityListener} on the postLoad event to allow the usage of
     * translation keys here.
     *
     * @var string
     */
    protected $name;
    /**
     * @var string
     *
     * @ORM\Column(name="_r_group_code", type="string", length=6, nullable=false, options={"fixed":true})
     */
    protected $groupCode;
    /**
     * @var string
     *
     * @ORM\Column(name="_r_group_name", type="string", length=60, nullable=false)
     */
    protected $groupName;
    /**
     * @var Collection<int, UserRoleInCustomerInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer", mappedBy="role")
     */
    protected $userRoleInCustomers;

    public function __construct()
    {
        $this->userRoleInCustomers = new ArrayCollection();
    }

    /**
     * Some methods need this. For example, array_unique().
     */
    public function __toString(): string
    {
        return $this->ident ?? '';
    }

    /**
     * @deprecated use {@link RoleInterface::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * Set code.
     *
     * @param string $code
     *
     * @return RoleInterface
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set Name.
     *
     * @param string $name
     *
     * @return RoleInterface
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get Name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set GroupCode.
     *
     * @param string $groupCode
     *
     * @return RoleInterface
     */
    public function setGroupCode($groupCode)
    {
        $this->groupCode = $groupCode;

        return $this;
    }

    /**
     * Get GroupCode.
     *
     * @return string
     */
    public function getGroupCode()
    {
        return $this->groupCode;
    }

    /**
     * Set GroupName.
     *
     * @param string $groupName
     *
     * @return RoleInterface
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;

        return $this;
    }

    /**
     * Get groupName.
     *
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    public function addUserRoleInCustomer(UserRoleInCustomerInterface $userRoleInCustomer): void
    {
        if (!$this->userRoleInCustomers->contains($userRoleInCustomer)) {
            $this->userRoleInCustomers->add($userRoleInCustomer);
            // $userRoleInCustomer->($this);
        }
    }

    public function getUserRoleInCustomers(): Collection
    {
        return $this->userRoleInCustomers;
    }

    public function removeUserRoleInCustomer(UserRoleInCustomerInterface $userRoleInCustomer)
    {
        $this->userRoleInCustomers->removeElement($userRoleInCustomer);
    }
}
