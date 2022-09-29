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

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity()
 */
class MaillaneConnection implements UuidEntityInterface
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
     * The ID of the corresponding account in Maillane
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=true, unique=true)
     */
    private $maillaneAccountId;

    /**
     * The email address that can be used to send statements via emails into the procedure.
     *
     * The value may be `null`, which simply implies that the procedure has not been given
     * an import email address (yet).
     *
     * Corresponds with the account name in Maillane
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=254, nullable=true, unique=true)
     *
     * @Assert\Email(mode="strict")
     */
    private $recipientEmailAddress;

    /**
     * Email addresses that are allowed to import email into this procedure by sending an
     * email containing its content.
     *
     * Corresponds with users in Maillane
     *
     * @var Collection<int, EmailAddress>
     *
     * @ORM\ManyToMany(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\EmailAddress",
     *     cascade={"persist"})
     * @ORM\JoinTable(
     *     name="maillane_allowed_sender_email_address",
     *     joinColumns={@ORM\JoinColumn(name="maillane_connection_id", referencedColumnName="id", nullable=false)},
     *     inverseJoinColumns={@ORM\JoinColumn(name="email_address_id", referencedColumnName="id", nullable=false)})
     */
    protected $allowedSenderEmailAddresses;

    /**
     * every maillaneconnection belong to a procedure
     * It fully depends on permissions and availability of the external Maillane service.
     *
     * @var Procedure
     *
     * @ORM\OneToOne(targetEntity="Procedure", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable = false,  referencedColumnName="_p_id")
     */
    private $procedure;

    public function __construct(Procedure $procedure)
    {
        $this->allowedSenderEmailAddresses = new ArrayCollection();
        $this->procedure = $procedure;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setMaillaneAccountId(string $maillaneAccountId): void
    {
        $this->maillaneAccountId = $maillaneAccountId;
    }

    public function getMaillaneAccountId(): ?string
    {
        return $this->maillaneAccountId;
    }

    public function setRecipientEmailAddress(string $recipientEmailAddress): void
    {
        $this->recipientEmailAddress = $recipientEmailAddress;
    }

    public function getRecipientEmailAddress(): ?string
    {
        return $this->recipientEmailAddress;
    }

    /**
     * @return Collection<int, EmailAddress>
     */
    public function getAllowedSenderEmailAddresses(): Collection
    {
        return $this->allowedSenderEmailAddresses;
    }

    /**
     * @param Collection<int, EmailAddress> $allowedSenderEmailAddresses
     */
    public function setAllowedSenderEmailAddresses(Collection $allowedSenderEmailAddresses): void
    {
        $this->allowedSenderEmailAddresses = $allowedSenderEmailAddresses;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }
}
