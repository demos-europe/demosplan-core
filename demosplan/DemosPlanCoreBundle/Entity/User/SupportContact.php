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

use DemosEurope\DemosplanAddon\Contracts\Entities\SupportContactInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\SupportContactConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(uniqueConstraints={@UniqueConstraint(name="customer_title_unique", columns={"customer", "title"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SupportContactRepository")
 */
#[SupportContactConstraint]
class SupportContact extends CoreEntity implements UuidEntityInterface, SupportContactInterface
{
    use TimestampableEntity;

    /**
     * These constants represent all possible values the property
     * {@link SupportContact::$supportType} can hold. This type is used to distinguish between support contacts used in
     * different locations with different context.
     * For example: A SupportContact can of type customerLogin in order to be shown under /idp/login/error on keycloak
     * authentication failure - or it can be of type customer to be shown under /informationen - or even without any
     * customer relation as type platform as a general support contact visible throughout all customers.
     */
    final public const SUPPORT_CONTACT_TYPE_DEFAULT = 'customer';
    final public const SUPPORT_CONTACT_TYPE_CUSTOMER_LOGIN = 'customerLogin';
    final public const SUPPORT_CONTACT_TYPE_PLATFORM = 'platform';

    /**
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private ?string $id = null;

    public function __construct(
        /**
         * @ORM\Column(name="type", type="string", length=255, nullable=false, options={"default":"customer"})
         */
        #[Assert\Choice(choices: [
            SupportContact::SUPPORT_CONTACT_TYPE_DEFAULT,
            SupportContact::SUPPORT_CONTACT_TYPE_CUSTOMER_LOGIN,
            SupportContact::SUPPORT_CONTACT_TYPE_PLATFORM,
        ], message: 'invalid support type')]
        private readonly string $supportType,
        /**
         * @ORM\Column(name="title", type="string", length=255, nullable=true)
         */
        #[Assert\NotBlank(allowNull: true)]
        private ?string $title,
        /**
         * @ORM\Column(name="phone_number", type="string", length=255, nullable=true)
         */
        #[Assert\NotBlank(allowNull: true)]
        private ?string $phoneNumber,
        /**
         * @ORM\Column(type="string", length=255, name="email_address", nullable=true)
         */
        #[Assert\Email(mode: 'strict')]
        private ?string $eMailAddress,
        /**
         * @ORM\Column(name="text", type="text", nullable=true)
         */
        private ?string $text,
        /**
         * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer", inversedBy="contacts")
         *
         * @ORM\JoinColumn(name="customer", referencedColumnName="_c_id", nullable=true)
         */
        private ?Customer $customer,
        /**
         * @ORM\Column(name="visible", type="boolean", options={"default":false})
         */
        private bool $visible = false
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSupportType(): string
    {
        return $this->supportType;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getEMailAddress(): ?string
    {
        return $this->eMailAddress;
    }

    public function setEMailAddress(?string $eMailAddress): void
    {
        $this->eMailAddress = $eMailAddress;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): void
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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }
}
