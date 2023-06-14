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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureBehaviorDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureUiDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFormDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\ExclusiveProcedureOrProcedureTypeException;
use demosplan\DemosPlanCoreBundle\Exception\FunctionalLogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProcedureType - Defines a specific type of a Procedure,
 * which is composed of a ProcedureUIDefinition, a ProcedureBehaviorDefinition and a StatementFormDefinition.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureTypeRepository")
 */
class ProcedureType extends CoreEntity implements UuidEntityInterface, ProcedureTypeInterface
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
     * @ORM\Column(type="datetime", nullable=false, options={"default":"CURRENT_TIMESTAMP"})
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default":"CURRENT_TIMESTAMP"})
     */
    private $modificationDate;

    /**
     * This column have to have a fixed length to allow uniqueness.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255, options={"fixed":true}, nullable=false, unique=true)
     *
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @var Collection<int, ProcedureInterface>
     *                                          One procedureType has many procedures. This is the inverse side.
     *
     * @ORM\OneToMany(targetEntity="Procedure", mappedBy="procedureType", cascade={"persist"})
     *
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private $procedures;

    /**
     * @var StatementFormDefinitionInterface
     *
     * @ORM\OneToOne(targetEntity="StatementFormDefinition", inversedBy="procedureType", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    private $statementFormDefinition;

    /**
     * @var ProcedureBehaviorDefinitionInterface
     *
     * @ORM\OneToOne(targetEntity="ProcedureBehaviorDefinition", inversedBy="procedureType", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    private $procedureBehaviorDefinition;

    /**
     * @var ProcedureUiDefinitionInterface
     *
     * @ORM\OneToOne(targetEntity="ProcedureUiDefinition", inversedBy="procedureType", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    private $procedureUiDefinition;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $description;

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    public function __construct(
        string $name,
        string $description,
        StatementFormDefinition $statementFormDefinition,
        ProcedureBehaviorDefinition $procedureBehaviorDefinition,
        ProcedureUiDefinition $procedureUiDefinition
    ) {
        $this->name = $name;
        $this->description = $description;

        $this->statementFormDefinition = $statementFormDefinition;
        $statementFormDefinition->setProcedureType($this);

        $this->procedureBehaviorDefinition = $procedureBehaviorDefinition;
        $procedureBehaviorDefinition->setProcedureType($this);

        $this->procedureUiDefinition = $procedureUiDefinition;
        $procedureUiDefinition->setProcedureType($this);

        $this->procedures = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function addProcedure(ProcedureInterface $procedure): void
    {
        if ($procedure->isMasterTemplate()) {
            throw new FunctionalLogicException('Masterblueprint should not be attached to a procedureType.');
        }

        $this->procedures->add($procedure);
        $procedure->setProcedureType($this);
    }

    public function getStatementFormDefinition(): StatementFormDefinitionInterface
    {
        return $this->statementFormDefinition;
    }

    public function getProcedureBehaviorDefinition(): ProcedureBehaviorDefinitionInterface
    {
        return $this->procedureBehaviorDefinition;
    }

    public function getProcedureUiDefinition(): ProcedureUiDefinitionInterface
    {
        return $this->procedureUiDefinition;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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
