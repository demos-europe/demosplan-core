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

use demosplan\DemosPlanCoreBundle\Repository\UserAccessControlRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
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
 *
 */
#[ORM\Table(name: 'user_access_control')]
#[ORM\UniqueConstraint(name: 'unique_user_orga_customer_role_permission', columns: ['user_id', 'orga_id', 'customer_id', 'role_id', 'permission'])]
#[ORM\Entity(repositoryClass: UserAccessControlRepository::class)]
class UserAccessControl extends CoreEntity implements UuidEntityInterface, UserAccessControlInterface
{
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected string $id;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    #[ORM\Column(name: 'permission', type: 'string', length: 255, nullable: false)]
    protected string $permission = '';

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false)]
    private DateTime $creationDate;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(name: 'modification_date', type: 'datetime', nullable: false)]
    private DateTime $modificationDate;

    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: '_u_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected UserInterface $user;

    #[ORM\JoinColumn(name: 'orga_id', referencedColumnName: '_o_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Orga::class)]
    protected OrgaInterface $organisation;

    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: '_r_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Role::class)]
    protected RoleInterface $role;

    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: '_c_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
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
