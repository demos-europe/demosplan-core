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
use DemosEurope\DemosplanAddon\Contracts\Entities\AccessControlInterface;
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
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\AccessControlRepository")
 *
 * @ORM\Table(name="access_control", uniqueConstraints={@ORM\UniqueConstraint(name="unique_orga_customer_role_permission", columns={"orga_id", "customer_id", "role_id", "permission"})})
 */
class AccessControl extends CoreEntity implements UuidEntityInterface, AccessControlInterface
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
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="orga_id", referencedColumnName="_o_id", nullable=false, onDelete="CASCADE")
     */
    protected OrgaInterface $organisation;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinColumn(referencedColumnName="_r_id", nullable=false, onDelete="CASCADE")
     */
    protected RoleInterface $role;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=false, onDelete="CASCADE")
     */
    protected CustomerInterface $customer;

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

    public function setOrga(?OrgaInterface $orga): void
    {
        $this->organisation = $orga;
    }

    public function setRole(?RoleInterface $role): void
    {
        $this->role = $role;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }
}
