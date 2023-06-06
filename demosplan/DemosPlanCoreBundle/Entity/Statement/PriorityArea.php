<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\PriorityAreaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFragmentInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Table(name="_priority_area",uniqueConstraints={@UniqueConstraint(name="key_idx", columns={"_pa_key"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\PriorityAreaRepository")
 */
class PriorityArea extends CoreEntity implements UuidEntityInterface, PriorityAreaInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pa_id", type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="_pa_key", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $key;

    /**
     * @var string
     *
     * @ORM\Column(name="_pa_type", type="string", length=36, options={"fixed":true, "default":NULL}, nullable=true)
     */
    protected $type;

    /**
     * @var Collection<int, StatementInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="priorityAreas")
     *
     * @ORM\JoinTable(
     *     name="_statement_priority_area",
     *     joinColumns={@ORM\JoinColumn(name="_pa_id", referencedColumnName="_pa_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id")}
     * )
     */
    protected $statements;

    /**
     * @var Collection<int, StatementFragmentInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment", mappedBy="priorityAreas", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_statement_fragment_priority_area",
     *     joinColumns={@ORM\JoinColumn(name="_pa_id", referencedColumnName="_pa_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="sf_id", referencedColumnName="sf_id")}
     * )
     */
    protected $statementFragments;

    public function __construct()
    {
        $this->statements = new ArrayCollection();
        $this->statementFragments = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getKey();
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
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
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return ArrayCollection
     */
    public function getStatements()
    {
        return $this->statements;
    }

    /**
     * @param ArrayCollection $statements
     */
    public function setStatements($statements)
    {
        $this->statements = $statements;
    }

    /**
     * Add Statement.
     *
     * @param StatementInterface $statement
     *
     * @return bool - true if the given statement was added to this priorityArea, otherwise false
     */
    public function addStatement($statement)
    {
        $successful = false;
        if (!$this->statements->contains($statement)) {
            $successful = $this->statements->add($statement);
        }

        return $successful;
    }

    /**
     * Remove Statement.
     *
     * @param StatementInterface $statement
     */
    public function removeStatement($statement)
    {
        if ($this->statements->contains($statement)) {
            $this->statements->removeElement($statement);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getStatementFragments()
    {
        return $this->statementFragments;
    }

    /**
     * Add StatementFragment.
     *
     * @param StatementFragmentInterface $fragment
     */
    public function addStatementFragment($fragment)
    {
        if (!$this->statementFragments->contains($fragment)) {
            $this->statementFragments->add($fragment);
        }
    }

    /**
     * Remove StatementFragment.
     *
     * @param StatementFragmentInterface $fragment
     */
    public function removeStatementFragment($fragment)
    {
        if ($this->statementFragments->contains($fragment)) {
            $this->statementFragments->removeElement($fragment);
        }
    }
}
