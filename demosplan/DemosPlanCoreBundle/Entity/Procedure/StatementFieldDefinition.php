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
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * StatementFieldDefinition - A part of a StatementFormDefinition.
 * Defines the availability of a customizable fields on a statement (participation).
 *
 * @ORM\Table(uniqueConstraints={
 *     @UniqueConstraint(columns={"statement_form_definition_id", "name"}),
 *     @UniqueConstraint(columns={"statement_form_definition_id", "order_number"})
 * })
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanProcedureBundle\Repository\StatementFieldDefinitionRepository")
 */
class StatementFieldDefinition extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    private $name;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $enabled;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $required = true;

    /**
     * @var StatementFormDefinition
     *
     * @ORM\ManyToOne(targetEntity="StatementFormDefinition", inversedBy="fieldDefinitions")
     * @JoinColumn(referencedColumnName="id", nullable=false)
     */
    private $statementFormDefinition;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", nullable=false, options={"default":0})
     */
    private $orderNumber;

    public function __construct(
        string $fieldName,
        StatementFormDefinition $statementFormDefinition,
        int $orderNumber,
        bool $enabled,
        bool $required
    ) {
        $this->enabled = $enabled;
        $this->required = $required;
        $this->name = $fieldName;
        $this->orderNumber = $orderNumber;
        $this->statementFormDefinition = $statementFormDefinition;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatementFormDefinition(): StatementFormDefinition
    {
        return $this->statementFormDefinition;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    public function getOrderNumber(): int
    {
        return $this->orderNumber;
    }
}
