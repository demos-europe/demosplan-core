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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureUiDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\ExclusiveProcedureOrProcedureTypeConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\ExclusiveProcedureOrProcedureTypeException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProcedureUiDefinition - Defines the customizable parts of the Form/UI of a Procedure.
 * A ProcedureUiDefinition should never have an direct relationship to a Procedure and to a ProcedureType.
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureUiDefinitionRepository")
 *
 * @ExclusiveProcedureOrProcedureTypeConstraint()
 */
class ProcedureUiDefinition extends CoreEntity implements UuidEntityInterface, ProcedureUiDefinitionInterface
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
     * @ORM\Column(type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $modificationDate;

    /**
     * In case of ProcedureUiDefinition has no related Procedure, that means that this ProcedureUiDefinition
     * was created by a customer and therefore defined as a origin ProcedureUiDefinition.
     * Furthermore ProcedureUiDefinition without a related Procedure will have a related ProcedureType.
     * A ProcedureUiDefinition with a direct relation to a Procedure
     * as well as a direct relation to a ProcedureType, indicates invalid data.
     *
     * @var Procedure|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="procedureUiDefinition")
     *
     * @JoinColumn(referencedColumnName="_p_id")
     */
    private $procedure;

    /**
     * In case of ProcedureUiDefinition has no related ProcedureType,
     * it is copied from a ProcedureUiDefinition related to a ProcedureType and was attached to a Procedure.
     * Therefore a ProcedureUiDefinition without a ProcedureType will have a related Procedure.
     *
     * @var ProcedureType|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType", mappedBy="procedureUiDefinition")
     *
     * @JoinColumn()
     */
    private $procedureType;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $mapHintDefault = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $statementFormHintStatement = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $statementFormHintPersonalData = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $statementFormHintRecheck = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $statementFormHintDataProtection = '';

    /**
     * This text is shown after a non-manual statement was submitted. It may include a
     * placeholder for the external ID of the statement which will be automatically
     * replaced by the actual external ID when the text is shown.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=500, nullable=false, options={"default":""})
     */
    #[Assert\Length(min: 0, max: 500, maxMessage: 'procedureUiDefinition.statementPublicSubmitConfirmationText.maxLength', options: ['allowEmptyString' => true])]
    #[Assert\NotNull]
    private $statementPublicSubmitConfirmationText = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getProcedure(): ?Procedure
    {
        return $this->procedure;
    }

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    public function setProcedure(ProcedureInterface $procedure): void
    {
        if ($this->procedureType instanceof ProcedureType) {
            throw new ExclusiveProcedureOrProcedureTypeException('. This ProcedureUiDefinition is already related to a ProcedureType.
                A ProcedureUiDefinition can not be set to a Procedure and to a ProcedureType');
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
    public function setProcedureType(ProcedureTypeInterface $procedureType): void
    {
        if ($this->procedure instanceof Procedure) {
            throw new ExclusiveProcedureOrProcedureTypeException('. This ProcedureUiDefinition is already related to a Procedure.
                A ProcedureUiDefinition can not be set to a Procedure and to a ProcedureType');
        }
        $this->procedureType = $procedureType;
    }

    public function getMapHintDefault(): string
    {
        return $this->mapHintDefault;
    }

    public function setMapHintDefault(string $mapHintDefault): void
    {
        $this->mapHintDefault = $mapHintDefault;
    }

    public function getStatementFormHintStatement(): string
    {
        return $this->statementFormHintStatement;
    }

    public function setStatementFormHintStatement(string $statementFormHintStatement): void
    {
        $this->statementFormHintStatement = $statementFormHintStatement;
    }

    public function getStatementFormHintPersonalData(): string
    {
        return $this->statementFormHintPersonalData;
    }

    public function setStatementFormHintPersonalData(string $statementFormHintPersonalData): void
    {
        $this->statementFormHintPersonalData = $statementFormHintPersonalData;
    }

    public function getStatementFormHintRecheck(): string
    {
        return $this->statementFormHintRecheck;
    }

    public function setStatementFormHintRecheck(string $statementFormHintRecheck): void
    {
        $this->statementFormHintRecheck = $statementFormHintRecheck;
    }

    public function getStatementFormHintDataProtection(): string
    {
        return $this->statementFormHintDataProtection;
    }

    public function setStatementFormHintDataProtection(string $statementFormHintDataProtection): void
    {
        $this->statementFormHintDataProtection = $statementFormHintDataProtection;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    public function getStatementPublicSubmitConfirmationText(): string
    {
        return $this->statementPublicSubmitConfirmationText;
    }

    public function setStatementPublicSubmitConfirmationText(string $statementPublicSubmitConfirmationText): void
    {
        $this->statementPublicSubmitConfirmationText = $statementPublicSubmitConfirmationText;
    }
}
