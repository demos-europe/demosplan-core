<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePersonInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedurePersonRepository")
 */
class ProcedurePerson implements UuidEntityInterface, ProcedurePersonInterface
{
    /**
     * @var string|null `null` if this instance was created but not persisted yet
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    private $streetName;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    private $streetNumber;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    private $city;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    private $postalCode;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[Assert\Email]
    private $emailAddress;

    /**
     * Each item in this collection references a statement for which this person has submitted a similar statement.
     * However, the latter one is unknown and may or may not have been entered into the application.
     *
     * @var Collection<int, Statement>
     *
     * @ORM\ManyToMany(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement",
     *     mappedBy="similarStatementSubmitters",
     *     cascade={"persist"},
     * )
     *
     * @ORM\JoinTable(
     *     name="similar_statement_submitter",
     *     joinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="statement_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="id", referencedColumnName="submitter_id")}
     * )
     */
    private Collection $similarForeignStatements;

    public function __construct(/**
     * @ORM\Column(type="text", nullable=false)
     */
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    private string $fullName, /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false)
     */
    private Procedure $procedure)
    {
        $this->similarForeignStatements = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): ProcedurePerson
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getStreetNameWithStreetNumber()
    {
        if (null === $this->streetName) {
            return null;
        }

        return null === $this->streetNumber
            ? $this->streetName
            : "$this->streetName $this->streetNumber";
    }

    public function getStreetName(): ?string
    {
        return $this->streetName;
    }

    public function setStreetName(?string $streetName): ProcedurePerson
    {
        $this->streetName = $streetName;

        return $this;
    }

    public function getStreetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function setStreetNumber(?string $streetNumber): ProcedurePerson
    {
        $this->streetNumber = $streetNumber;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): ProcedurePerson
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCodeWithCity(): ?string
    {
        return null === $this->city
            ? $this->postalCode
            : "$this->postalCode $this->city";
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): ProcedurePerson
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function setEmailAddress(?string $emailAddress): ProcedurePerson
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    /**
     * @return Collection<int, Statement>
     */
    public function getSimilarForeignStatements(): Collection
    {
        return $this->similarForeignStatements;
    }

    /**
     * Adds the given statement to the similarForeignStatements if not already containing.
     */
    public function addSimilarForeignStatement(StatementInterface $similarForeignStatement): void
    {
        if (!$this->similarForeignStatements->contains($similarForeignStatement)) {
            $this->similarForeignStatements->add($similarForeignStatement);
        }

        if (!$similarForeignStatement->getSimilarStatementSubmitters()->contains($this)) {
            $similarForeignStatement->addSimilarStatementSubmitter($this);
        }
    }

    public function removeSimilarForeignStatement(StatementInterface $similarForeignStatement): void
    {
        if ($this->similarForeignStatements->contains($similarForeignStatement)) {
            $this->similarForeignStatements->removeElement($similarForeignStatement);
            $similarForeignStatement->removeSimilarStatementSubmitter($this);
        }
    }
}
