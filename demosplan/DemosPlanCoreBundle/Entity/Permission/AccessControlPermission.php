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
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * This entity represents a permission for a specific role, customer and organisation.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\AccessControlPermissionRepository")
 *
 * @ORM\Table(name="access_control_permission", uniqueConstraints={@ORM\UniqueConstraint(name="unique_orga_customer_role_permission", columns={"orga_id", "customer_id", "role_id", "permission"})})
 */
class AccessControlPermission extends CoreEntity implements UuidEntityInterface
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
     * @ORM\Column(name="permission", type="string", nullable=false)
     */
    protected string $permission = '';

    /**
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private DateTime $creationDate;

    /**
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private DateTime $modificationDate;

    /**
     * @var OrgaInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="orga_id", referencedColumnName="_o_id", nullable=true, onDelete="CASCADE")
     */
    protected $organisation;

    /**
     * @var RoleInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinColumn(referencedColumnName="_r_id", nullable=true, onDelete="CASCADE")
     */
    protected $role;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=true, onDelete="CASCADE")
     */
    protected ?CustomerInterface $customer;

    public function getId(): string
    {
        return $this->id;
    }

    public function getPermissionName(): string
    {
        return $this->permission;
    }

    public function setPermissionName(string $permission): void
    {
        $this->permission = $permission;
    }

    /**
     * @param OrgaInterface $orga
     */
    public function setOrga($orga)
    {
        $this->organisation = $orga;
    }

    /**
     * @param RoleInterface $role
     */
    public function setRole($role)
    {
        $this->role = $role;
    }

    /**
     * @param CustomerInterface $customer
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
    }
}
