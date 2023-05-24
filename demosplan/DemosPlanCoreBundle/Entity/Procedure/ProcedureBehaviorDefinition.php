<?php

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
use demosplan\DemosPlanCoreBundle\Exception\ExclusiveProcedureOrProcedureTypeException;
use demosplan\DemosPlanProcedureBundle\Constraint\ExclusiveProcedureOrProcedureTypeConstraint;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * ProcedureBehaviorDefinition - Defines the customizable parts of the behavior of a Procedure.
 * A ProcedureBehaviorDefinition should never have an direct relationship to a Procedure and to a ProcedureType.
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanProcedureBundle\Repository\ProcedureBehaviorDefinitionRepository")
 *
 * @ExclusiveProcedureOrProcedureTypeConstraint()
 */
class ProcedureBehaviorDefinition extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    /**
     * In case of ProcedureBehaviorDefinition has no related Procedure, that means that this ProcedureBehaviorDefinition
     * was created by a customer and therefore defined as a origin ProcedureBehaviorDefinition.
     * Furthermore ProcedureBehaviorDefinition without a related Procedure will have a related ProcedureType.
     * A ProcedureBehaviorDefinition with a direct relation to a Procedure
     * as well as a direct relation to a ProcedureType, indicates invalid data.
     *
     * @var Procedure|null
     *
     * @ORM\OneToOne(targetEntity="Procedure", mappedBy="procedureBehaviorDefinition")
     *
     * @JoinColumn(referencedColumnName="_p_id")
     */
    private $procedure;

    /**
     * In case of ProcedureBehaviorDefinition has no related ProcedureType,
     * it is copied from a ProcedureBehaviorDefinition related to a ProcedureType and was attached to a Procedure.
     * Therefore a ProcedureBehaviorDefinition without a ProcedureType will have a related Procedure.
     *
     * @var ProcedureType|null
     *
     * @ORM\OneToOne(targetEntity="ProcedureType", mappedBy="procedureBehaviorDefinition")
     *
     * @JoinColumn() // Without this, Doctrine doesn't add the column to table, so please don't delete.
     */
    private $procedureType;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $allowedToEnableMap = true;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    private $hasPriorityArea = false;

    /**
     * If 'true', then only guests can participate and see the procedure. Of course, this setting does not exclude
     * planners.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    private $participationGuestOnly = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProcedure(): ?Procedure
    {
        return $this->procedure;
    }

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    public function setProcedure(Procedure $procedure): void
    {
        if ($this->procedureType instanceof ProcedureType) {
            throw new ExclusiveProcedureOrProcedureTypeException('. This ProcedureBehaviorDefinition is already related to a ProcedureType.
                A ProcedureBehaviorDefinition can not be set to a Procedure and to a ProcedureType');
        }
        $this->procedure = $procedure;
    }

    public function getProcedureType(): ?ProcedureType
    {
        return $this->procedureType;
    }

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    public function setProcedureType(ProcedureType $procedureType): void
    {
        if ($this->procedure instanceof Procedure) {
            throw new ExclusiveProcedureOrProcedureTypeException('. This ProcedureBehaviorDefinition is already related to a Procedure.
                A ProcedureBehaviorDefinition can not be set to a Procedure and to a ProcedureType');
        }
        $this->procedureType = $procedureType;
    }

    public function isAllowedToEnableMap(): bool
    {
        return $this->allowedToEnableMap;
    }

    public function setAllowedToEnableMap(bool $allowedToEnableMap): void
    {
        $this->allowedToEnableMap = $allowedToEnableMap;
    }

    public function hasPriorityArea(): bool
    {
        return $this->hasPriorityArea;
    }

    public function setHasPriorityArea(bool $hasPriorityArea): void
    {
        $this->hasPriorityArea = $hasPriorityArea;
    }

    public function isParticipationGuestOnly(): bool
    {
        return $this->participationGuestOnly;
    }

    public function setParticipationGuestOnly(bool $participationGuestOnly): void
    {
        $this->participationGuestOnly = $participationGuestOnly;
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
