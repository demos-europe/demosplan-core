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
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_department", uniqueConstraints={@ORM\UniqueConstraint(name="_d_gw_id", columns={"_d_gw_id"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository")
 */
class Department extends CoreEntity implements UuidEntityInterface
{
    /**
     * For technical reasons, a department is always required, even if none is given.
     * This is the default name.
     */
    public const DEFAULT_DEPARTMENT_NAME = 'Keine Abteilung';

    /**
     * @var string|null
     *
     * @ORM\Column(name="_d_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_d_name", type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="_d_code", type="string", length=128, nullable=true)
     */
    protected $code;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_d_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_d_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var bool
     *
     * @ORM\Column(name="_d_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_d_gw_id", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $gwId;

    /**
     * Diese Eigenschaft ist aus Legacygründen definiert, um das DB-Schema zu erhalten
     * $orga enthält die einzelne Organisation.
     *
     * @var Collection<int, Orga>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", mappedBy="departments")
     */
    protected $orgas;

    /**
     * Organisation des Departments.
     *
     * @var Orga
     */
    protected $orga;

    /**
     * @var Collection<int, Address>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Address")
     *
     * @ORM\JoinTable(
     *     name="_department_addresses_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_d_id", referencedColumnName="_d_id", onDelete="RESTRICT")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_a_id", referencedColumnName="_a_id", onDelete="RESTRICT")}
     * )
     */
    protected $addresses;

    /**
     * Aus Legacygründen wird dies als Many-to-Many-Association modelliert, damit das DB-Schema erhalten bleibt
     * Fachlich ist es derzeit eine One-to-Many-Association.
     *
     * @var Collection<int, User>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User", inversedBy="departments", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_department_users_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_d_id", referencedColumnName="_d_id", onDelete="RESTRICT")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", onDelete="RESTRICT")}
     * )
     */
    protected $users;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->orgas = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Set dName.
     *
     * @return Department
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    // @improve T17643

    /**
     * Get dName.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set dCode.
     *
     * @param string $code
     *
     * @return Department
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get dCode.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set dCreatedDate.
     *
     * @param DateTime $createdDate
     *
     * @return Department
     */
    public function setCreatedDate(DateTimeInterface $createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * Get dCreatedDate.
     *
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * Set dModifiedDate.
     *
     * @param DateTime $modifiedDate
     *
     * @return Department
     */
    public function setModifiedDate(DateTimeInterface $modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * Get dModifiedDate.
     *
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * Set dDeleted.
     *
     * @param bool $deleted
     *
     * @return Department
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set dGwId.
     *
     * @param string|null $gwId
     *
     * @return Department
     */
    public function setGwId($gwId)
    {
        $this->gwId = $gwId;

        return $this;
    }

    /**
     * Get dGwId.
     *
     * @return string|null
     */
    public function getGwId()
    {
        return $this->gwId;
    }

    /**
     * Organisation des Departments.
     *
     * @return Orga|null
     */
    public function getOrga()
    {
        if ($this->orgas instanceof Collection && 1 == $this->orgas->count()) {
            return $this->orgas->first();
        }

        return null;
    }

    /**
     * Will return an empty string, if no Orga is set.
     *
     * @return string
     */
    public function getOrgaName()
    {
        if (!is_null($this->getOrga())) {
            return $this->getOrga()->getName();
        }

        return '';
    }

    /**
     * Add Orga to this Department.
     */
    public function addOrga(Orga $orga)
    {
        if ($this->orgas instanceof Collection) {
            if (!$this->orgas->contains($orga)) {
                $this->orgas->add($orga);
            }
        } else {
            $this->orgas = new ArrayCollection([$orga]);
        }
    }

    /**
     * @return Address
     */
    public function getAddress()
    {
        if ($this->addresses instanceof Collection && 1 == $this->addresses->count()) {
            return $this->addresses->first();
        }

        return null;
    }

    /**
     * @return ArrayCollection Address[]
     */
    public function getAddresses()
    {
        return $this->addresses;
    }

    /**
     * @param array $addresses
     *
     * @return $this
     */
    public function setAddresses($addresses)
    {
        $this->addresses = new ArrayCollection($addresses);

        return $this;
    }

    /**
     * Add Address to Orga.
     *
     * @param Address $address
     */
    public function addAddress($address)
    {
        if ($this->addresses instanceof Collection) {
            $this->addresses->add($address);
        } else {
            $this->addresses = new ArrayCollection([$address]);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getAllUsers()
    {
        return $this->users;
    }

    /**
     * Returns all users of this department, which are not deleted === true.
     *
     * @return \Tightenco\Collect\Support\Collection
     */
    public function getUsers()
    {
        /** @var User[] $allUser */
        $allUser = $this->users;
        $notDeletedUser = collect([]);

        foreach ($allUser as $user) {
            if (!$user->isDeleted()) {
                $notDeletedUser->push($user);
            }
        }

        return $notDeletedUser;
    }

    /**
     * @param array $users
     *
     * @return $this
     */
    public function setUsers($users)
    {
        $this->users = new ArrayCollection($users);

        return $this;
    }

    /**
     * Add user to this Department, if not already exists in the collection.
     */
    public function addUser(User $user)
    {
        if ($this->users instanceof Collection) {
            if (!$this->users->contains($user)) {
                $this->users->add($user);
            }
        } else {
            $this->users = new ArrayCollection([$user]);
        }
        // Add department to Userentity
        $user->setDepartment($this);
    }

    /**
     * Remove user from Department.
     */
    public function removeUser(User $user)
    {
        if ($this->users instanceof Collection) {
            if ($this->users->contains($user)) {
                $this->users->removeElement($user);
            }
        } else {
            $this->users = new ArrayCollection([$user]);
        }
        // Remove department from Userentity
        $user->removeDepartment($this);
        $user->unsetDepartment();
    }

    public function getEntityContentChangeIdentifier(): string
    {
        return $this->getOrgaName().' '.$this->getName();
    }
}
