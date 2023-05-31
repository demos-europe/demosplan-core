<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerCounty;
use demosplan\DemosPlanCoreBundle\EventListener\CountyEntityListener;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_county")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CountyRepository")
 */
class County extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_c_id", type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Collection<int, CustomerCounty>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\CustomerCounty", mappedBy="county", cascade={"persist"})
     */
    protected $customerCounties
    ;

    /**
     * @var string
     *
     * @ORM\Column(name="_c_name", type="string", length=36, nullable=false)
     */
    protected $name;

    /**
     * This is an unmapped property that has no strict connection to anything in the database.
     * Instead, it is filled via a lifecycle event listener {@link CountyEntityListener}.
     * It always contains the counties e-mail address for the current customer.
     *
     * @var string
     */
    protected $email = '';

    /**
     * @var Collection<int, Statement>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="counties")
     *
     * @ORM\JoinTable(
     *     name="_statement_county",
     *     joinColumns={@ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id")}
     * )
     */
    protected $statements;

    /**
     * @var Collection<int, StatementFragment>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment", mappedBy="counties", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_statement_fragment_county",
     *     joinColumns={@ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="sf_id", referencedColumnName="sf_id")}
     * )
     */
    protected $statementFragments;

    public function __construct()
    {
        $this->statements = new ArrayCollection();
        $this->statementFragments = new ArrayCollection();
        $this->customerCounties = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return Collection<int, CustomerCounty>
     */
    public function getCustomerCounties(): Collection
    {
        return $this->customerCounties;
    }

    /**
     * @param Collection<int, CustomerCounty> $customerCounties
     */
    public function setCustomerCounties(Collection $customerCounties): void
    {
        $this->customerCounties = $customerCounties;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
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
     * @param Statement $statement
     *
     * @return bool - true if the given statement was added to this county, otherwise false
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
     * @param Statement $statement
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
     * @param StatementFragment $fragment
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
     * @param StatementFragment $fragment
     */
    public function removeStatementFragment($fragment)
    {
        if ($this->statementFragments->contains($fragment)) {
            $this->statementFragments->removeElement($fragment);
        }
    }
}
