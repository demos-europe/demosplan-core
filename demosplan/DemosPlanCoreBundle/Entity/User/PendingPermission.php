<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores pending permissions that should be applied to organizations when they are created.
 *
 * This entity represents a "permission intent" - a permission that should be automatically
 * applied to organizations of a specific type when they are created for a customer.
 *
 * Typical workflow:
 * 1. Customer is created with --queue-permission flag
 * 2. PendingPermission entries are created and persisted
 * 3. When an organization of matching type is created, pending permissions are applied
 * 4. After application, pending permissions can remain (for future orgs) or be deleted
 *
 * @ORM\Table(
 *     name="pending_permission",
 *     indexes={
 *
 *         @ORM\Index(name="idx_pp_customer", columns={"_pp_customer_id"}),
 *         @ORM\Index(name="idx_pp_orga_type", columns={"_pp_orga_type"}),
 *         @ORM\Index(name="idx_pp_customer_orga_type", columns={"_pp_customer_id", "_pp_orga_type"})
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\PendingPermissionRepository")
 */
class PendingPermission extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pp_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * The customer for which this permission should be applied.
     *
     * @var CustomerInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="_pp_customer_id", referencedColumnName="_c_id", nullable=false, onDelete="CASCADE")
     */
    protected $customer;

    /**
     * The permission name to be applied (e.g., 'feature_admin_new_procedure').
     *
     * @var string
     *
     * @ORM\Column(name="_pp_permission", type="string", length=255, nullable=false)
     */
    protected $permission;

    /**
     * The role code that should have this permission (e.g., 'RMOPSA').
     *
     * @var string
     *
     * @ORM\Column(name="_pp_role_code", type="string", length=10, nullable=false)
     */
    protected $roleCode;

    /**
     * The organization type this permission applies to (e.g., 'PLANNING_AGENCY', 'PUBLIC_AGENCY').
     *
     * @var string
     *
     * @ORM\Column(name="_pp_orga_type", type="string", length=50, nullable=false)
     */
    protected $orgaType;

    /**
     * When this pending permission was created.
     *
     * @var DateTime
     *
     * @ORM\Column(name="_pp_created_at", type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * Optional description or reason for this permission.
     *
     * @var string|null
     *
     * @ORM\Column(name="_pp_description", type="text", nullable=true)
     */
    protected $description;

    /**
     * Whether this pending permission should be automatically deleted after first application.
     * If false, it remains and applies to all future organizations of this type.
     *
     * @var bool
     *
     * @ORM\Column(name="_pp_auto_delete", type="boolean", nullable=false, options={"default":false})
     */
    protected $autoDelete = false;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function getRoleCode(): string
    {
        return $this->roleCode;
    }

    public function setRoleCode(string $roleCode): self
    {
        $this->roleCode = $roleCode;

        return $this;
    }

    public function getOrgaType(): string
    {
        return $this->orgaType;
    }

    public function setOrgaType(string $orgaType): self
    {
        $this->orgaType = $orgaType;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isAutoDelete(): bool
    {
        return $this->autoDelete;
    }

    public function setAutoDelete(bool $autoDelete): self
    {
        $this->autoDelete = $autoDelete;

        return $this;
    }
}
