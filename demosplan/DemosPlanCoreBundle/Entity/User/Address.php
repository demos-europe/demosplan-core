<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\AddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="_address")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\AddressRepository")
 */
class Address extends CoreEntity implements UuidEntityInterface, AddressInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_code", type="string", length=10, nullable=true)
     *
     * @Assert\Length(min=0, max=10)
     */
    protected $code;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_street", type="string", length=100, nullable=true)
     *
     * @Assert\Length(min=1, max=100)
     */
    protected $street;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_street_1", type="string", length=100, nullable=true)
     *
     * @Assert\Length(min=1, max=100)
     */
    protected $street1;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_postalcode", type="string", length=10, nullable=true)
     *
     * @Assert\Length(min=5, max=5)
     */
    protected $postalcode;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_city", type="string", length=100, nullable=true)
     *
     * @Assert\Length(min=1, max=100)
     */
    protected $city = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_region", type="string", length=45, nullable=true)
     */
    protected $region;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_state", type="string", length=65, nullable=true)
     */
    protected $state;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_postofficebox", type="string", length=10, nullable=true)
     */
    protected $postofficebox;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_phone", type="string", length=30, nullable=true)
     */
    protected $phone;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_fax", type="string", length=30, nullable=true)
     */
    protected $fax;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_email", type="string", length=364, nullable=true)
     *
     * @Assert\Email(message="email.address.invalid")
     */
    protected $email;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_a_url", type="string", length=364, nullable=true)
     *
     * @Assert\Url
     */
    protected $url;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_a_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_a_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var bool
     *
     * @ORM\Column(name="_a_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false,  options={"default":""})
     */
    protected $houseNumber = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setCode(?string $code): Address
    {
        $this->code = $code;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setStreet(?string $street): Address
    {
        $this->street = $street;

        return $this;
    }

    public function getStreet(): string
    {
        return $this->street ?? '';
    }

    public function setStreet1(?string $street1): Address
    {
        $this->street1 = $street1;

        return $this;
    }

    public function getStreet1(): ?string
    {
        return $this->street1;
    }

    public function setPostalcode(?string $postalcode): Address
    {
        $this->postalcode = $postalcode;

        return $this;
    }

    public function getPostalcode(): string
    {
        return $this->postalcode ?? '';
    }

    public function setCity(?string $city): Address
    {
        $this->city = $city;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city ?? '';
    }

    public function setRegion(?string $region): Address
    {
        $this->region = $region;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setState(?string $state): Address
    {
        $this->state = $state;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setPostofficebox(?string $postofficebox): Address
    {
        $this->postofficebox = $postofficebox;

        return $this;
    }

    public function getPostofficebox(): ?string
    {
        return $this->postofficebox;
    }

    public function setPhone(?string $phone): Address
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setFax(?string $fax): Address
    {
        $this->fax = $fax;

        return $this;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setEmail(?string $email): Address
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setUrl(?string $url): Address
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setCreatedDate(DateTimeInterface $createdDate): Address
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }

    public function setModifiedDate(DateTimeInterface $modifiedDate): Address
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    public function getModifiedDate(): DateTime
    {
        return $this->modifiedDate;
    }

    public function setDeleted(bool $deleted): Address
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(string $houseNumber): void
    {
        $this->houseNumber = $houseNumber;
    }
}
