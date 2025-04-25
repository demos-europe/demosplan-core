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
    protected ?string $id;

    /**
     * Virtual property
     * Readable Phase name.
     */
    protected string $name = '';

    /**
     * @ORM\Column(name="phase_key", type="string", nullable=false)
     */
    protected string $key;

    /**
     * Virtual Property bound on phase configuration in procedurephases.yml.
     */
    protected string $permissionSet;

    /**
     * @ORM\Column(type="string", length=25, nullable=false, options={"default":""})
     */
    protected string $step = '';

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
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $designatedSwitchDate = null;

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

    public function __construct(string $key = 'configuration', string $step = '')
    {
        $this->key = $key;
        $this->step = $step;
        $this->permissionSet = ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getPermissionSet(): string
    {
        return $this->permissionSet;
    }

    public function setPermissionSet(string $permissionSet): void
    {
        $this->permissionSet = $permissionSet;
    }

    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getCreationDate(): DateTime
    {
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

    public function getDesignatedSwitchDate(): ?DateTime
    {
        return $this->designatedSwitchDate;
    }

    public function setDesignatedSwitchDate(?DateTime $designatedSwitchDate): void
    {
        $this->designatedSwitchDate = $designatedSwitchDate;
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

    public function getStep(): string
    {
        return $this->step;
    }

    public function setStep(string $step): void
    {
        $this->step = $step;
    }

    public function copyValuesFromPhase(ProcedurePhaseInterface $sourcePhase): void
    {
        $this->key = $sourcePhase->key;
        $this->step = $sourcePhase->step;
        $this->name = $sourcePhase->name;
        $this->designatedEndDate = $sourcePhase->designatedEndDate;
        $this->designatedSwitchDate = $sourcePhase->designatedSwitchDate;
        $this->designatedPhase = $sourcePhase->designatedPhase;
        $this->permissionSet = $sourcePhase->permissionSet;
        $this->startDate = $sourcePhase->startDate;
        $this->endDate = $sourcePhase->endDate;
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
