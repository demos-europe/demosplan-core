<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Permission;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserAccessControlInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a user-specific permission for a specific user, role, customer and organisation.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\UserAccessControlRepository")
 *
 * @ORM\Table(name="user_access_control", uniqueConstraints={@ORM\UniqueConstraint(name="unique_user_orga_customer_role_permission", columns={"user_id", "orga_id", "customer_id", "role_id", "permission"})})
 */
class UserAccessControl extends CoreEntity implements UuidEntityInterface, UserAccessControlInterface
{
    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected string $id;

    /**
     * @ORM\Column(name="permission", type="string", length=255, nullable=false)
     */
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    protected string $permission = '';

    /**
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="creation_date", type="datetime", nullable=false)
     */
    private DateTime $creationDate;

    /**
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="modification_date", type="datetime", nullable=false)
     */
    private DateTime $modificationDate;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", nullable=false, onDelete="CASCADE")
     */
    protected UserInterface $user;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="orga_id", referencedColumnName="_o_id", nullable=false, onDelete="CASCADE")
     */
    protected OrgaInterface $organisation;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinColumn(name="role_id", referencedColumnName="_r_id", nullable=false, onDelete="CASCADE")
     */
    protected RoleInterface $role;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="_c_id", nullable=false, onDelete="CASCADE")
     */
    protected CustomerInterface $customer;

    public function getId(): string
    {
        return $this->id;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): void
    {
        $this->permission = $permission;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getOrganisation(): OrgaInterface
    {
        return $this->organisation;
    }

    public function setOrganisation(OrgaInterface $organisation): void
    {
        $this->organisation = $organisation;
    }

    public function getRole(): RoleInterface
    {
        return $this->role;
    }

    public function setRole(RoleInterface $role): void
    {
        $this->role = $role;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }
}
