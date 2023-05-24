<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\AddressBookEntryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\AddressBookEntryRepository")
 */
class AddressBookEntry extends CoreEntity implements UuidEntityInterface, AddressBookEntryInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(type="string", nullable=true)
     */
    protected $name;

    /**
     * @var OrgaInterface
     *
     * Many address book entries have one organisation. This is the owning side.
     * (In Doctrine Many have to be the owning side in a ManyToOne relationship.)
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", inversedBy="addressBookEntries")
     *
     * @ORM\JoinColumn(referencedColumnName="_o_id", onDelete="CASCADE")
     */
    protected $organisation;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $emailAddress;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    public function __construct(string $name, string $emailAddress, Orga $organisation)
    {
        $this->setName($name);
        $this->setEmailAddress($emailAddress);
        $organisation->addAddressBookEntry($this);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress)
    {
        $this->emailAddress = $emailAddress;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getCreatedDate(): DateTimeInterface
    {
        return $this->createdDate;
    }

    /**
     * @param DateTime|DateTimeImmutable $createdDate
     */
    public function setCreatedDate(DateTimeInterface $createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getModifiedDate(): DateTimeInterface
    {
        return $this->modifiedDate;
    }

    /**
     * @param DateTime|DateTimeImmutable $modifiedDate
     */
    public function setModifiedDate(DateTimeInterface $modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;
    }

    public function getOrganisation(): Orga
    {
        return $this->organisation;
    }

    public function setOrganisation(OrgaInterface $organisation)
    {
        $this->organisation = $organisation;
    }
}
