<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_statement_attribute")
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanStatementBundle\Repository\StatementAttributeRepository")
 */
class StatementAttribute extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_sta_id", type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Statement|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="statementAttributes")
     * @ORM\JoinColumn(name="_sta_st_id", referencedColumnName="_st_id", onDelete="CASCADE")
     */
    protected $statement;

    /**
     * @var string
     */
    protected $statementId;

    /**
     * @var DraftStatement|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement", inversedBy="statementAttributes")
     * @ORM\JoinColumn(name="_sta_ds_id", referencedColumnName="_ds_id", onDelete="CASCADE")
     */
    protected $draftStatement;

    /**
     * @var string
     */
    protected $draftStatementId;
    /**
     * @var string
     *
     * @ORM\Column(name="_sta_type", type="string", length=50)
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="_sta_value", type="string", length=1024, nullable=true)
     */
    protected $value;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @param Statement $statement
     *
     * @return $this
     */
    public function setStatement($statement)
    {
        $this->statement = $statement;

        return $this;
    }

    /**
     * @return DraftStatement
     */
    public function getDraftStatement()
    {
        return $this->draftStatement;
    }

    /**
     * @param DraftStatement $draftStatement
     *
     * @return $this
     */
    public function setDraftStatement($draftStatement)
    {
        $this->draftStatement = $draftStatement;
        $draftStatement->addStatementAttribute($this);

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
