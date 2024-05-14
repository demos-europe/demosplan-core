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
use DemosEurope\DemosplanAddon\Contracts\Entities\AccessControlPermissionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Stores the information about the current phase of a procedure.
 * Currently there a two phases related to a procedure, therefore this Entity is related to the procedure twice.
 *
 * @ORM\Table(name="access_control_permissions")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\AccessControlPermissionRepository")
 */
class AccessControlPermission extends CoreEntity implements UuidEntityInterface, AccessControlPermissionInterface
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
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $deletionDate;

    /**
     * @var OrgaInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="orga_id", referencedColumnName="_o_id", nullable=true, onDelete="SET NULL")
     */
    protected $organisation;

    /**
     * @var RoleInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinColumn(referencedColumnName="_r_id", nullable=true, onDelete="SET NULL")
     */
    protected $role;

    /**
     * @var CustomerInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=true, onDelete="SET NULL")
     */
    protected $customer;

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
