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
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores a customer-specific procedure phase definition (Verfahrensschritt).
 * Each customer can define their own set of procedure phases for internal and external audiences.
 */
#[ORM\Table(name: 'procedure_phase_definition')]
#[ORM\Entity(repositoryClass: ProcedurePhaseDefinitionRepository::class)]
class ProcedurePhaseDefinition extends CoreEntity implements UuidEntityInterface, ProcedurePhaseDefinitionInterface
{
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected ?string $id = null;

    /**
     * The display name of this procedure phase definition.
     */
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    protected string $name = '';

    /**
     * The audience this phase belongs to.
     * Values: 'internal' (Institutionsbeteiligung) | 'external' (Öffentlichkeitsbeteiligung).
     */
    #[ORM\Column(type: 'string', length: 25, nullable: false)]
    protected string $audience = '';

    /**
     * Controls visibility and participation access for this phase.
     * Values: 'hidden' | 'read' | 'write'.
     *
     * For internal phases (audience = 'internal'), this governs invitable institutions ("Institutionen"):
     * * `'hidden'`: institutions cannot see the procedure or its planning documents; participation is not allowed
     * * `'read'`: invited institutions can see the procedure and its planning documents, but cannot participate
     * * `'write'`: invited institutions can see the procedure and its planning documents and can participate
     *
     * For external phases (audience = 'external'), this governs guests and citizens:
     * * `'hidden'`: guests and citizens cannot see the procedure or its planning documents; participation is not allowed
     * * `'read'`: guests and citizens can see the procedure and its planning documents, but cannot participate
     * * `'write'`: guests and citizens can see the procedure and its planning documents and can participate
     *
     * Note: planners always have 'write' access for procedures they own, regardless of this value.
     */
    #[Assert\Choice([
        ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN,
        ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_READ,
        ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_WRITE,
    ])]
    #[ORM\Column(type: 'string', length: 10, nullable: false)]
    protected string $permissionSet = 'hidden';

    /**
     * Optional participation state for this phase.
     * Values: null | 'finished' | 'participateWithToken'.
     */
    #[Assert\Choice([
        ProcedureInterface::PARTICIPATIONSTATE_FINISHED,
        ProcedureInterface::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN,
    ])]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    protected ?string $participationState = null;

    /**
     * Whether entering this phase marks the procedure as closed (archived).
     */
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    protected bool $closingPhase = false;

    /**
     * Indicates if a ProcedurePhaseDefinition is deleted or not.
     */
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    protected bool $isDeleted = false;

    /**
     * Sort order within the audience.
     * The order is independent per audience: both internal and external phases start at 0.
     */
    #[ORM\Column(type: 'integer', nullable: false, options: ['unsigned' => true, 'default' => 0])]
    protected int $orderInAudience = 0;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: '_c_id', nullable: true, onDelete: 'CASCADE')]
    protected ?CustomerInterface $customer = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    private DateTime $creationDate;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'update')]
    private DateTime $modificationDate;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $deletedDate = null;

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

    public function isClosingPhase(): bool
    {
        return $this->closingPhase;
    }

    public function setClosingPhase(bool $closingPhase): void
    {
        $this->closingPhase = $closingPhase;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setDeleted(bool $deleted): void
    {
        $this->isDeleted = $deleted;
        $this->deletedDate = $deleted ? new DateTime() : null;
    }

    public function getOrderInAudience(): int
    {
        return $this->orderInAudience;
    }

    public function setOrderInAudience(int $orderInAudience): void
    {
        $this->orderInAudience = $orderInAudience;
    }

    /**
     * The configuration phase ("Konfiguration") is always the first phase within its audience.
     * There is exactly one per audience and, unlike other phases, only its name may be edited -
     * its permissionSet and participationState are fixed.
     */
    public function isConfigurationPhase(): bool
    {
        return 0 === $this->orderInAudience;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
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

    public function getDeletedDate(): ?DateTime
    {
        return $this->deletedDate;
    }
}
