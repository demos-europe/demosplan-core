<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Stores a customer-specific procedure phase definition (Verfahrensschritt).
 * Each customer can define their own set of procedure phases for internal and external audiences.
 *
 * @ORM\Table(
 *     name="procedure_phase_definition",
 *     uniqueConstraints={
 *
 *         @ORM\UniqueConstraint(name="uniq_name_customer_audience", columns={"name", "customer_id", "audience"})
 *     }
 * )
 *
 * @ORM\Entity
 */
class ProcedurePhaseDefinition extends CoreEntity implements UuidEntityInterface
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
    protected ?string $id = null;

    /**
     * The display name of this procedure phase definition.
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected string $name = '';

    /**
     * The audience this phase belongs to.
     * Values: 'internal' (Institutionsbeteiligung) | 'external' (Öffentlichkeitsbeteiligung).
     *
     * @ORM\Column(type="string", length=25, nullable=false)
     */
    protected string $audience = '';

    /**
     * The permission set for this phase.
     * Values: 'hidden' | 'read' | 'write'.
     *
     * @ORM\Column(type="string", length=10, nullable=false)
     */
    protected string $permissionSet = 'hidden';

    /**
     * Optional participation state for this phase.
     * Values: null | 'finished' | 'participateWithToken'.
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    protected ?string $participationState = null;

    /**
     * Sort order within the audience.
     * The order is independent per audience: both internal and external phases start at 0.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned":true, "default":0})
     */
    protected int $orderInAudience = 0;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="_c_id", nullable=false, onDelete="CASCADE")
     */
    protected Customer $customer;

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

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function setAudience(string $audience): void
    {
        $this->audience = $audience;
    }

    public function getPermissionSet(): string
    {
        return $this->permissionSet;
    }

    public function setPermissionSet(string $permissionSet): void
    {
        $this->permissionSet = $permissionSet;
    }

    public function getParticipationState(): ?string
    {
        return $this->participationState;
    }

    public function setParticipationState(?string $participationState): void
    {
        $this->participationState = $participationState;
    }

    public function getOrderInAudience(): int
    {
        return $this->orderInAudience;
    }

    public function setOrderInAudience(int $orderInAudience): void
    {
        $this->orderInAudience = $orderInAudience;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getCreationDate(): DateTime
    {
        if (!isset($this->creationDate)) {
            $this->creationDate = new DateTime();
        }

        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        if (!isset($this->modificationDate)) {
            $this->modificationDate = new DateTime();
        }

        return $this->modificationDate;
    }
}
