<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="_support_contact")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SupportContactRepository")
 */
class SupportContact extends CoreEntity implements UuidEntityInterface
{
    use TimestampableEntity;

    /**
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private ?string $id;

    /**
     * @ORM\Column(name="title", type="string", length=255, nullable=true)
     */
    private ?string $title;

    /**
     * @ORM\Column(name="phone_number", type="string", length=255, nullable=true)
     */
    private ?string $phoneNumber;

    /**
     * @ORM\ManyToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\EmailAddress",
     *     cascade={"persist"})
     *
     * @ORM\JoinColumn(name="email_id",
     *     referencedColumnName="id",
     *     nullable = true)
     */
    #[Assert\Valid]
    private ?EmailAddress $eMailAddress;

    /**
     * @ORM\Column(name="text", type="text", nullable=true)
     */
    private ?string $text;

    /**
     * @ORM\Column(name="visible", type="boolean")
     */
    private bool $visible = false;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer", inversedBy="contacts")
     *
     * @ORM\JoinColumn(name="customer", referencedColumnName="_c_id", nullable=true)
     */
    private ?Customer $customer;

    public function __construct(
        ?string $title,
        ?string $phoneNumber,
        ?EmailAddress $emailAddress,
        ?string $text,
        ?Customer $customer,
        bool $visible
    ) {
        $this->title = $title;
        $this->phoneNumber = $phoneNumber;
        $this->eMailAddress = $emailAddress;
        $this->text = $text;
        $this->customer = $customer;
        $this->visible = $visible;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getEMailAddress(): string
    {
        return $this->eMailAddress;
    }

    public function setEMailAddress(string $eMailAddress): void
    {
        $this->eMailAddress = $eMailAddress;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }
}
