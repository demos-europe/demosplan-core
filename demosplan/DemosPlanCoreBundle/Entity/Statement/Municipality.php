<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\MunicipalityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFragmentInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_municipality", uniqueConstraints={@ORM\UniqueConstraint(name="official_municipality_key", columns={"official_municipality_key"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\MunicipalityRepository")
 */
class Municipality extends CoreEntity implements UuidEntityInterface, MunicipalityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_m_id", type="string", length=36, nullable=false, options={"fixed":true})
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
     * @ORM\Column(name="_m_name", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true, options={"fixed":true, "default":null})
     */
    protected $officialMunicipalityKey = null;

    /**
     * @var Collection<int, StatementInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="municipalities")
     *
     * @ORM\JoinTable(
     *     name="_statement_municipality",
     *     joinColumns={@ORM\JoinColumn(name="_m_id", referencedColumnName="_m_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id")}
     * )
     */
    protected $statements;

    /**
     * @var Collection<int, StatementFragmentInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment", mappedBy="municipalities", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="_m_id", referencedColumnName="_m_id")},
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
     * Return name in addition of officialMunicipalityKey if officialMunicipalityKey is set.
     *
     * @return string
     */
    public function getName()
    {
        $name = $this->name;
        $key = $this->officialMunicipalityKey;

        if (false === is_null($key) && 5 === strlen($key)) {
            $name = $name.' - '.$key;
        }

        return $name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @return bool - true if the given statement was added to this municipality, otherwise false
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

    /**
     * @param string|null $officialMunicipalityKey
     */
    public function setOfficialMunicipalityKey($officialMunicipalityKey)
    {
        $this->officialMunicipalityKey = $officialMunicipalityKey;
    }

    /**
     * @return string|null
     */
    public function getOfficialMunicipalityKey()
    {
        return $this->officialMunicipalityKey;
    }
}
