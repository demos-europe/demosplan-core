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
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFormDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\ExclusiveProcedureOrProcedureTypeConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\ExclusiveProcedureOrProcedureTypeException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * StatementFormDefinition - Holds a set of StatementFieldDefinitions
 * to define the availability of customizable fields on a statement (participation).
 * A StatementFormDefinition should never have an direct relationship to a Procedure and to a ProcedureType.
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementFormDefinitionRepository")
 *
 * @ExclusiveProcedureOrProcedureTypeConstraint()
 */
class StatementFormDefinition extends CoreEntity implements UuidEntityInterface, StatementFormDefinitionInterface
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
     * @var Collection<int, StatementFieldDefinition>
     *
     * @ORM\OneToMany(
     *      targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition",
     *      mappedBy="statementFormDefinition",
     *      cascade={"persist", "remove"}
     *     )
     *
     * @ORM\OrderBy({"orderNumber" = "ASC"})
     */
    private $fieldDefinitions;

    /**
     * In case of StatementFormDefinition has no related Procedure, that means that this StatementFormDefinition
     * was created by a customer and therefore defined as a origin StatementFormDefinition.
     * Furthermore StatementFormDefinition without a related Procedure will have a related ProcedureType.
     * A StatementFormDefinition with a direct relation to a Procedure
     * as well as a direct relation to a ProcedureType, indicates invalid data.
     *
     * @var Procedure|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="statementFormDefinition")
     *
     * @JoinColumn(referencedColumnName="_p_id")
     */
    private $procedure;

    /**
     * In case of StatementFormDefinition has no related ProcedureType,
     * it is copied from a StatementFormDefinition related to a ProcedureType and was attached to a Procedure.
     * Therefore a StatementFormDefinition without a ProcedureType will have a related Procedure.
     *
     * @var ProcedureType|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType", mappedBy="statementFormDefinition")
     *
     * @JoinColumn()
     */
    private $procedureType;

    public function __construct()
    {
        $fieldDefinitionsNames = [
            StatementFormDefinitionInterface::MAP_AND_COUNTY_REFERENCE                    => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::COUNTY_REFERENCE                            => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::NAME                                        => ['enabled' => true,    'required' => true],
            StatementFormDefinitionInterface::POSTAL_AND_CITY                             => ['enabled' => true,    'required' => false],
            StatementFormDefinitionInterface::GET_EVALUATION_MAIL_VIA_EMAIL               => ['enabled' => true,    'required' => false],
            StatementFormDefinitionInterface::GET_EVALUATION_MAIL_VIA_SNAIL_MAIL_OR_EMAIL => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::CITIZEN_XOR_ORGA_AND_ORGA_NAME              => ['enabled' => true,    'required' => true],
            StatementFormDefinitionInterface::STREET                                      => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::STREET_AND_HOUSE_NUMBER                     => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::PHONE                                       => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::EMAIL                                       => ['enabled' => true,    'required' => false],
            StatementFormDefinitionInterface::PHONE_OR_EMAIL                              => ['enabled' => false,   'required' => false],
            StatementFormDefinitionInterface::STATE_AND_GROUP_AND_ORGA_NAME_AND_POSITION  => ['enabled' => false,   'required' => false],
        ];

        $this->fieldDefinitions = new ArrayCollection();
        $orderNumber = 1;

        foreach ($fieldDefinitionsNames as $fieldDefinitionsName => $values) {
            $statementFieldDefinition = new StatementFieldDefinition($fieldDefinitionsName, $this, $orderNumber, $values['enabled'], $values['required']);
            $statementFieldDefinition->setId('n/a');
            $this->fieldDefinitions->add(
                $statementFieldDefinition
            );
            ++$orderNumber;
        }
    }

    /**
     * @return Collection<int, StatementFieldDefinition>
     */
    public function getFieldDefinitions(): Collection
    {
        return $this->fieldDefinitions;
    }

    /**
     * @return Collection<int, StatementFieldDefinition>
     */
    public function getEnabledFieldDefinitions(): Collection
    {
        return $this->fieldDefinitions->filter(
            fn (StatementFieldDefinition $fieldDefinition) => $fieldDefinition->isEnabled()
        );
    }

    public function getFieldDefinitionByName(string $name): ?StatementFieldDefinition
    {
        /** @var StatementFieldDefinition $fieldDefinition */
        foreach ($this->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition->getName() === $name) {
                return $fieldDefinition;
            }
        }

        return null;
    }

    public function isFieldDefinitionEnabled(string $name)
    {
        $fieldDefinition = $this->getFieldDefinitionByName($name);
        if (null === $fieldDefinition) {
            return false;
        }

        if (!$fieldDefinition->isEnabled()) {
            return false;
        }

        return true;
    }

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
            throw new ExclusiveProcedureOrProcedureTypeException('. This StatementFormDefinition is already related to a ProcedureType.
                A StatementFormDefinition can not be set to a Procedure and to a ProcedureType');
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
            throw new ExclusiveProcedureOrProcedureTypeException('. This StatementFormDefinition is already related to a Procedure.
                A StatementFormDefinition can not be set to a Procedure and to a ProcedureType');
        }
        $this->procedureType = $procedureType;
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
