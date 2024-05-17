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
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFieldDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFormDefinitionInterface;
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
 *
 *     @UniqueConstraint(columns={"statement_form_definition_id", "name"}),
 *     @UniqueConstraint(columns={"statement_form_definition_id", "order_number"})
 * })
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementFieldDefinitionRepository")
 */
class StatementFieldDefinition extends CoreEntity implements UuidEntityInterface, StatementFieldDefinitionInterface
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

    public function __construct(
        /**
         * @ORM\Column(type="string", nullable=false)
         */
        private string $name,
        /**
         * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition", inversedBy="fieldDefinitions")
         *
         * @JoinColumn(referencedColumnName="id", nullable=false)
         */
        private StatementFormDefinition $statementFormDefinition,
        /**
         * @ORM\Column(type="smallint", nullable=false, options={"default":0})
         */
        private int $orderNumber,
        /**
         * @ORM\Column(type="boolean", nullable=false)
         */
        private bool $enabled,
        /**
         * @ORM\Column(type="boolean", nullable=false, options={"default":true})
         */
        private bool $required
    ) {
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

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getStatementFormDefinition(): StatementFormDefinitionInterface
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
