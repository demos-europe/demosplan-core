<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity()
 */
class ProcedurePerson implements UuidEntityInterface
{
    /**
     * @var string|null `null` if this instance was created but not persisted yet
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false)
     */
    private $procedure;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     */
    private $fullName;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     *
     * @Assert\NotBlank(allowNull=true, normalizer="trim")
     */
    private $streetName;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     *
     * @Assert\NotBlank(allowNull=true, normalizer="trim")
     */
    private $streetNumber;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     *
     * @Assert\NotBlank(allowNull=true, normalizer="trim")
     */
    private $city;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     *
     * @Assert\NotBlank(allowNull=true, normalizer="trim")
     */
    private $postalCode;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     *
     * @Assert\NotBlank(allowNull=true, normalizer="trim")
     * @Assert\Email()
     */
    private $emailAddress;

    public function __construct(string $fullName, Procedure $procedure)
    {
        $this->fullName = $fullName;
        $this->procedure = $procedure;
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
}
