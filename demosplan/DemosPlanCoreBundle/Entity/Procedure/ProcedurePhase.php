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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores the information about the current phase of a procedure.
 * Currently there a two phases related to a procedure, therefore this Entity is related to the procedure twice.
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseRepository")
 */
class ProcedurePhase extends CoreEntity implements UuidEntityInterface, ProcedurePhaseInterface
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
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $startDate;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $endDate;

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
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    protected ?string $designatedPhase = null;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition")
     *
     * @ORM\JoinColumn(name="phase_definition_id", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected ProcedurePhaseDefinition $phaseDefinition;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition")
     *
     * @ORM\JoinColumn(name="designated_phase_definition_id", referencedColumnName="id", nullable=true, onDelete="RESTRICT")
     */
    protected ?ProcedurePhaseDefinition $designatedPhaseDefinition = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $designatedSwitchDate = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected ?int $designatedSwitchDateTimestamp = null;

    /**
     * OnDelete set NULL at this site, will set the userID to null in case of the user will be deleted.
     * Doing this by a doctrine relation is not simply possible because,
     * the user has no defined relation in its class.
     *
     * @var UserInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     */
    protected $designatedPhaseChangeUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $designatedEndDate = null;

    /**
     * @ORM\Column(type="smallint", nullable=false, options={"unsigned":true, "default":1})
     */
    #[Assert\Positive]
    protected int $iteration = 1;

    /**
     * @ORM\Column(type="string", length=25, nullable=false, options={"default":""})
     * @deprecated phase keys will be removed
     */
    protected string $step = '';

    /**
     * @ORM\Column(name="phase_key", type="string", nullable=false)
     * @deprecated phase keys will be removed
     */
    protected string $key = 'configuration';

    public function __construct(ProcedurePhaseDefinition $phaseDefinition)
    {
        $this->phaseDefinition = $phaseDefinition;
        $this->endDate = new DateTime();
        $this->startDate = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @deprecated phase keys will be removed
     */
    public function getName(): string
    {
        return '';
    }

    /**
     * @deprecated phase keys will be removed
     */
    public function setName(string $name): void
    {

    }

    /**
     * @deprecated phase keys will be removed
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @deprecated phase keys will be removed
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * @deprecated use ProcedurePhaseDefinition instead
     */
    public function getPermissionSet(): string
    {
        return '';
    }

    /**
     * @deprecated use ProcedurePhaseDefinition instead
     */
    public function setPermissionSet(string $permissionSet): void
    {
    }

    public function getStartDate(): DateTime
    {
        if (!isset($this->startDate)) {
            $this->startDate = new DateTime();
        }

        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): DateTime
    {
        if (!isset($this->endDate)) {
            $this->endDate = new DateTime();
        }

        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): void
    {
        $this->endDate = $endDate;
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
        return $this->modificationDate;
    }

    public function getDesignatedPhase(): ?string
    {
        return $this->designatedPhase;
    }

    public function setDesignatedPhase(?string $designatedPhase): void
    {
        $this->designatedPhase = $designatedPhase;
    }

    public function getPhaseDefinition(): ProcedurePhaseDefinition
    {
        return $this->phaseDefinition;
    }

    public function setPhaseDefinition(ProcedurePhaseDefinition $phaseDefinition): void
    {
        $this->phaseDefinition = $phaseDefinition;
    }

    public function getDesignatedPhaseDefinition(): ?ProcedurePhaseDefinition
    {
        return $this->designatedPhaseDefinition;
    }

    public function setDesignatedPhaseDefinition(?ProcedurePhaseDefinition $designatedPhaseDefinition): void
    {
        $this->designatedPhaseDefinition = $designatedPhaseDefinition;
    }

    public function getDesignatedSwitchDate(): ?DateTime
    {
        return $this->designatedSwitchDate;
    }

    public function setDesignatedSwitchDate(?DateTime $designatedSwitchDate): void
    {
        $this->designatedSwitchDate = $designatedSwitchDate;
    }

    public function getDesignatedSwitchDateTimestamp(): ?int
    {
        return $this->designatedSwitchDateTimestamp;
    }

    public function setDesignatedSwitchDateTimestamp(?int $designatedSwitchDateTimestamp): void
    {
        $this->designatedSwitchDateTimestamp = $designatedSwitchDateTimestamp;
    }

    public function getDesignatedPhaseChangeUser(): ?UserInterface
    {
        return $this->designatedPhaseChangeUser;
    }

    public function setDesignatedPhaseChangeUser(?UserInterface $designatedPhaseChangeUser): void
    {
        $this->designatedPhaseChangeUser = $designatedPhaseChangeUser;
    }

    public function getDesignatedEndDate(): ?DateTime
    {
        return $this->designatedEndDate;
    }

    public function setDesignatedEndDate(?DateTime $designatedEndDate): void
    {
        $this->designatedEndDate = $designatedEndDate;
    }

    /**
     * @deprecated phase keys will be removed
     */
    public function getStep(): string
    {
        return $this->step;
    }

    /**
     * @deprecated phase keys will be removed
     */
    public function setStep(string $step): void
    {
        $this->step = $step;
    }

    public function copyValuesFromPhase(ProcedurePhaseInterface $sourcePhase): void
    {
        $this->designatedEndDate = $sourcePhase->getDesignatedEndDate();
        $this->designatedSwitchDate = $sourcePhase->getDesignatedSwitchDate();
        $this->designatedSwitchDateTimestamp = $sourcePhase->getDesignatedSwitchDateTimestamp();
        $this->designatedPhase = $sourcePhase->getDesignatedPhase();
        $this->startDate = $sourcePhase->getStartDate();
        $this->endDate = $sourcePhase->getEndDate();

        if ($sourcePhase instanceof self) {
            $this->phaseDefinition = $sourcePhase->getPhaseDefinition();
            $this->designatedPhaseDefinition = $sourcePhase->getDesignatedPhaseDefinition();
        }
    }

    public function getIteration(): int
    {
        return $this->iteration;
    }

    public function setIteration(int $iteration): void
    {
        $this->iteration = $iteration;
    }
}
