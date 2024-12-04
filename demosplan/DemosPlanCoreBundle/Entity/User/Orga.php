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
use DemosEurope\DemosplanAddon\Contracts\Entities\AddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\BrandingInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DepartmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\InstitutionTagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\MasterToebInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\SluggedEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Illuminate\Support\Collection as IlluminateCollection;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     name="_orga",
 *     uniqueConstraints={
 *
 *         @ORM\UniqueConstraint(
 *             name="_o_gw_id",
 *             columns={"_o_gw_id"}
 *         )
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\OrgaRepository")
 *
 * @ORM\AssociationOverrides({
 *
 *      @ORM\AssociationOverride(name="slugs",
 *          joinTable=@ORM\JoinTable(
 *              joinColumns=@ORM\JoinColumn(name="o_id", referencedColumnName="_o_id"),
 *              inverseJoinColumns=@ORM\JoinColumn(name="s_id", referencedColumnName="id")
 *          )
 *      )
 * })
 */
class Orga extends SluggedEntity implements OrgaInterface, Stringable
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_o_name", type="string", length=255, nullable=true)
     */
    protected $name;
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_gateway_name", type="string", length=255, nullable=true)
     */
    protected $gatewayName = '';
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_code", type="string", length=128, nullable=true)
     */
    protected $code;
    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_o_created_date", type="datetime", nullable=false)
     */
    protected $createdDate;
    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_o_modified_date", type="datetime", nullable=false)
     */
    protected $modifiedDate;
    /**
     * Comma separated list of cc-Email addresses.
     *
     * @var string|null
     *
     * @ORM\Column(name="_o_cc_email2", type="string", length=4096, nullable=true)
     */
    protected $ccEmail2;
    /**
     * This E-mail is used as "Koordinatoremail der Fachbehörden".
     *
     * @var string|null
     *
     * @ORM\Column(name="_o_email_reviewer_admin", type="string", length=4096, nullable=true)
     */
    #[Assert\Email(message: 'email.address.invalid')]
    protected $emailReviewerAdmin;
    /**
     * @var bool
     *
     * @ORM\Column(name="_o_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;
    /**
     * @var bool
     *
     * @ORM\Column(name="_o_showname", type="boolean", nullable=false, options={"default":false})
     */
    protected $showname = false;
    /**
     * @var bool
     *           Is this orga listed in public toeb list
     *
     * @ORM\Column(name="_o_showlist", type="boolean", nullable=false, options={"default":false})
     */
    protected $showlist = true;
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_gw_id", type="string", length=250, nullable=true)
     */
    protected $gwId;
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_competence", type="text", length=65535, nullable=true)
     */
    protected $competence;
    /**
     * In case of this organisation is a public agency and is in use, logically this property
     * will be filled.
     * Beteiligungsemail - "Sie erhalten Benachrichtigungen an diese Adresse bezüglich Ihrer Stellungnahme(n).".
     *
     * @var string|null
     *
     * @ORM\Column(name="_o_email2", type="string", length=364, nullable=true)
     */
    #[Assert\Email(message: 'email.address.invalid')]
    protected $email2;
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_contact_person", type="string", length=256, nullable=true)
     */
    protected $contactPerson;
    /**
     * @var int|null This is currently nullable, but we're phasing that out. Eventually, it should
     *               not be nullable. This is why the setter requires an non-nullable int.
     *
     * @ORM\Column(name="_o_paper_copy", type="integer", length=2, nullable=true, options={"unsigned":true})
     */
    protected $paperCopy;
    /**
     * @var string|null
     *
     * @ORM\Column(name="_o_paper_copy_spec", type="string", length=4096, nullable=true)
     */
    protected $paperCopySpec;
    /**
     * @var Collection<int, AddressInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Address", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_orga_addresses_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="cascade")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_a_id", referencedColumnName="_a_id", onDelete="cascade")}
     * )
     */
    #[Assert\All([new Assert\Type(type: 'demosplan\DemosPlanCoreBundle\Entity\User\Address')])]
    #[Assert\Type(type: Collection::class)]
    protected $addresses;
    /**
     * $customers not mapped to a Table because they are now retrieved from {@link Orga::$statusInCustomers}.
     *
     * @var Collection
     */
    protected $customers;
    /**
     * Virtuelle Eigenschaft, den Wert der Adresse ausgibt.
     *
     * @var string
     */
    protected $street;
    /**
     * Virtuelle Eigenschaft, den Wert der Adresse ausgibt.
     *
     * @var string
     */
    protected $postalcode;
    /**
     * Virtuelle Eigenschaft, den Wert der Adresse ausgibt.
     *
     * @var string
     */
    protected $city;
    /**
     * Virtuelle Eigenschaft, den Wert der Adresse ausgibt.
     *
     * @var string
     */
    protected $phone;
    /**
     * Data privacy protection setting of the orga which is displayed as legal requirement on the website.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/impressum/ Wiki: Impressum / Datenschutz
     *
     * @ORM\Column(name="data_protection", type="text", length=65535, nullable=false)
     *
     * @var string
     */
    protected $dataProtection = '';
    /**
     * Data privacy protection setting of the orga which is displayed as legal requirement on the website.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/impressum/ Wiki: Impressum / Datenschutz
     *
     * @ORM\Column(name="imprint", type="text", length=65535, nullable=false)
     *
     * @var string
     */
    protected $imprint = '';
    /**
     * @var ArrayCollection array[]
     */
    protected $notifications;
    /**
     * Aus Legacygründen wird dies als Many-to-Many-Association modelliert, damit das DB-Schema erhalten bleibt
     * Fachlich ist es derzeit eine One-to-Many-Association.
     *
     * @var Collection<int, UserInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User", inversedBy="orga",
     *                 cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_orga_users_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="RESTRICT")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", onDelete="RESTRICT")}
     * )
     */
    protected $users;
    /**
     * Aus Legacygründen wird dies als Many-to-Many-Association modelliert, damit das DB-Schema erhalten bleibt
     * Fachlich ist es derzeit eine One-to-Many-Association.
     *
     * @var Collection<int, DepartmentInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department", inversedBy="orgas",
     *                 cascade={"persist", "all"})
     *
     * @ORM\JoinTable(
     *     name="_orga_departments_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="RESTRICT")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_d_id", referencedColumnName="_d_id", onDelete="RESTRICT")}
     * )
     */
    protected $departments;
    /**
     ** @var Collection<int, Procedure>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="orga")
     */
    protected $procedures;
    /**
     * @var Collection<int, Procedure>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="organisation")
     */
    protected $procedureInvitations;
    /**
     * Virtual property for statement submissionType saved in Settings.
     *
     * @var string
     */
    protected $submissionType = self::STATEMENT_SUBMISSION_TYPE_DEFAULT;
    /**
     * @var Collection<int, AddressBookEntryInterface>
     *                                                 One organisation has many address book entries. This is the inverse side.
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry", mappedBy="organisation")
     */
    protected $addressBookEntries;
    /**
     * @var Collection<int, OrgaStatusInCustomerInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer", mappedBy="orga", cascade={"persist"})
     */
    protected $statusInCustomers;
    /**
     * @var MasterToebInterface|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb", mappedBy="orga")
     */
    protected $masterToeb;
    /**
     * @var BrandingInterface|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Branding", cascade={"persist", "remove"})
     */
    protected $branding;
    /**
     * The {@link Procedure} entities this organisation (if a planning agency) is allowed to administrate.
     *
     * @var Collection<int, ProcedureInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="planningOffices")
     */
    protected $administratableProcedures;
    /**
     * @var Collection<int,InstitutionTagInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag", inversedBy="taggedInstitutions", cascade={"persist", "remove"})
     *
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(referencedColumnName="_o_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    protected $assignedTags;

    public function __construct()
    {
        $this->addressBookEntries = new ArrayCollection();
        $this->addresses = new ArrayCollection();
        $this->customers = new ArrayCollection();
        $this->departments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->procedures = new ArrayCollection();
        $this->slugs = new ArrayCollection();
        $this->statusInCustomers = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->administratableProcedures = new ArrayCollection();
        $this->assignedTags = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @deprecated use {@link Orga::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getNameLegal(): ?string
    {
        return $this->getName();
    }

    public function getGatewayName(): ?string
    {
        return $this->gatewayName;
    }

    public function setGatewayName(?string $gatewayName): self
    {
        $this->gatewayName = $gatewayName;

        return $this;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param DateTime|DateTimeImmutable $createdDate
     */
    public function setCreatedDate(DateTimeInterface $createdDate): self
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getCreatedDate(): DateTimeInterface
    {
        return $this->createdDate;
    }

    /**
     * @param DateTime|DateTimeImmutable $modifiedDate
     */
    public function setModifiedDate(DateTimeInterface $modifiedDate): self
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getModifiedDate(): DateTimeInterface
    {
        return $this->modifiedDate;
    }

    public function getCcEmail2(): ?string
    {
        return $this->ccEmail2;
    }

    public function setCcEmail2(?string $ccEmail2): self
    {
        $this->ccEmail2 = $ccEmail2;

        return $this;
    }

    public function getEmailReviewerAdmin(): ?string
    {
        return $this->emailReviewerAdmin;
    }

    public function setEmailReviewerAdmin(?string $emailReviewerAdmin): self
    {
        $this->emailReviewerAdmin = $emailReviewerAdmin;

        return $this;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function isDeleted(): bool
    {
        return $this->getDeleted();
    }

    public function setShowname(bool $showname): self
    {
        $this->showname = $showname;

        return $this;
    }

    public function getShowname(): bool
    {
        return $this->showname;
    }

    public function isShowname(): bool
    {
        return $this->getShowname();
    }

    public function setShowlist(bool $showlist): self
    {
        $this->showlist = $showlist;

        return $this;
    }

    public function getShowlist(): bool
    {
        return $this->showlist;
    }

    public function isShowlist(): bool
    {
        return $this->getShowlist();
    }

    public function setGwId(?string $gwId): self
    {
        $this->gwId = $gwId;

        return $this;
    }

    public function getGwId(): ?string
    {
        return $this->gwId;
    }

    public function setCompetence(?string $competence): self
    {
        $this->competence = $competence;

        return $this;
    }

    public function getCompetence(): ?string
    {
        return $this->competence;
    }

    public function setEmail2(?string $email2): self
    {
        $this->email2 = $email2;

        return $this;
    }

    public function getEmail2(): ?string
    {
        return $this->email2;
    }

    /**
     * todo: Please note that, ideally, if a string is returned, it should be a valid
     *       email address. However, currently, this is not the case. It may be an empty
     *       string, a single hyphen or an untrimmed email addresses as well. Code
     *       adjustments and database migrations would be needed to fix this.
     */
    public function getParticipationEmail(): ?string
    {
        return $this->getEmail2();
    }

    public function setParticipationEmail(string $emailAddress): self
    {
        return $this->setEmail2($emailAddress);
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): self
    {
        $this->contactPerson = $contactPerson;

        return $this;
    }

    public function setPaperCopy(int $paperCopy): self
    {
        $this->paperCopy = $paperCopy;

        return $this;
    }

    public function getPaperCopy(): ?int
    {
        return $this->paperCopy;
    }

    public function setPaperCopySpec(?string $paperCopySpec): self
    {
        $this->paperCopySpec = $paperCopySpec;

        return $this;
    }

    public function getPaperCopySpec(): ?string
    {
        return $this->paperCopySpec;
    }

    /**
     * @param string $subdomain
     *
     * @return OrgaType[]
     */
    public function getOrgaTypes($subdomain = '', bool $acceptedOnly = false): array
    {
        $orgaTypes = [];
        /** @var OrgaStatusInCustomer $customerOrgaTypes */
        foreach ($this->getStatusInCustomers() as $customerOrgaTypes) {
            $customer = $customerOrgaTypes->getCustomer();
            if ($subdomain === $customer->getSubdomain()) {
                if ($acceptedOnly && OrgaStatusInCustomer::STATUS_ACCEPTED !== $customerOrgaTypes->getStatus()) {
                    continue;
                }
                $orgaTypes[] = $customerOrgaTypes->getOrgaType();
            }
        }

        return $orgaTypes;
    }

    /**
     * @param string $subdomain
     *
     * @return string[]
     */
    public function getTypes($subdomain = '', bool $acceptedOnly = false): array
    {
        $orgaTypeNames = [];
        $orgaTypes = $this->getOrgaTypes($subdomain, $acceptedOnly);
        foreach ($orgaTypes as $orgaType) {
            if ($orgaType instanceof OrgaType) {
                $orgaTypeNames[] = $orgaType->getName();
            }
        }

        return $orgaTypeNames;
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    /**
     * @param AddressInterface[] $addresses
     */
    public function setAddresses(array $addresses): self
    {
        $this->addresses->clear();
        $this->addresses = new ArrayCollection($addresses);

        return $this;
    }

    /**
     * @return Address|false note that the return types of first() are the object or false
     */
    public function getAddress()
    {
        return $this->addresses->first();
    }

    public function addAddress(AddressInterface $address): self
    {
        $this->addresses->add($address);

        return $this;
    }

    /**
     * @return string can be street (string) or empty string
     */
    public function getStreet(): string
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getStreet() ?? '';
        }

        return '';
    }

    public function setStreet($street): self
    {
        $this->setAddressValue('street', $street);

        return $this;
    }

    public function getHouseNumber(): string
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getHouseNumber() ?? '';
        }

        return '';
    }

    public function setHouseNumber($houseNumber): self
    {
        $this->setAddressValue('houseNumber', $houseNumber);

        return $this;
    }

    public function getState(): string
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getState() ?? '';
        }

        return '';
    }

    public function setState($state): self
    {
        $this->setAddressValue('state', $state);

        return $this;
    }

    public function getFax()
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getFax();
        }

        return '';
    }

    /**
     * @param string $fax
     */
    public function setFax($fax): self
    {
        $this->setAddressValue('fax', $fax);

        return $this;
    }

    public function getPostalcode(): string
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getPostalcode() ?? '';
        }

        return '';
    }

    /**
     * @param string $postalcode
     */
    public function setPostalcode($postalcode): self
    {
        $this->setAddressValue('postalcode', $postalcode);

        return $this;
    }

    public function getCity()
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getCity();
        }

        return '';
    }

    /**
     * @param string $city
     */
    public function setCity($city): self
    {
        $this->setAddressValue('city', $city);

        return $this;
    }

    public function getPhone(): string
    {
        if ($this->addresses instanceof Collection && false !== $this->addresses->first()) {
            return $this->addresses->first()->getPhone() ?? '';
        }

        return '';
    }

    /**
     * @param string $phone
     */
    public function setPhone($phone): self
    {
        $this->setAddressValue('phone', $phone);

        return $this;
    }

    public function getDataProtection(): string
    {
        return $this->dataProtection;
    }

    public function setDataProtection(string $dataProtection): self
    {
        $this->dataProtection = $dataProtection;

        return $this;
    }

    public function getImprint(): string
    {
        return $this->imprint;
    }

    public function setImprint(string $imprint): self
    {
        $this->imprint = $imprint;

        return $this;
    }

    public function addAdministratableProcedure(ProcedureInterface $procedure): bool
    {
        $alreadyPresent = $this->administratableProcedures->contains($procedure);
        if (!$alreadyPresent) {
            $this->administratableProcedures->add($procedure);
        }

        return !$alreadyPresent;
    }

    /**
     * @return Collection<int, Procedure>
     */
    public function getAdministratableProcedures(): Collection
    {
        return $this->administratableProcedures;
    }

    /**
     * Save single Value in associated Address.
     */
    protected function setAddressValue($key, $value): self
    {
        $method = 'set'.ucfirst((string) $key);
        $address = $this->addresses->first();
        if (!$address instanceof Address) {
            $address = new Address();
        }
        if (method_exists($address, $method)) {
            $address->$method($value);
            $this->setAddresses([$address]);
        }

        return $this;
    }

    public function getNotifications(): array
    {
        if ($this->notifications instanceof Collection) {
            return $this->notifications->toArray();
        }

        return [];
    }

    /**
     * @param array $notifications
     */
    public function setNotifications($notifications): self
    {
        $this->notifications = new ArrayCollection($notifications);

        return $this;
    }

    public function addNotification($notification): self
    {
        $this->notifications->add($notification);

        return $this;
    }

    /**
     * @return ArrayCollection|IlluminateCollection
     */
    public function getAllUsers()
    {
        return $this->users;
    }

    public function getUsers(): IlluminateCollection
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
     * @param array<int,UserInterface> $users
     */
    public function setUsers($users): self
    {
        $this->users = new ArrayCollection($users);

        return $this;
    }

    public function addUser(UserInterface $user): self
    {
        if ($this->users instanceof Collection) {
            if (!$this->users->contains($user)) {
                $this->users->add($user);
            }
        } else {
            $this->users = new ArrayCollection([$user]);
        }
        $user->setOrga($this);

        return $this;
    }

    public function removeUser(UserInterface $user): self
    {
        if ($this->users instanceof Collection) {
            $this->users->removeElement($user);
        }
        $user->unsetOrgas();

        return $this;
    }

    public function getDepartments(): IlluminateCollection
    {
        $nonDeletedDepartments = $this->departments->filter(
            static fn (Department $department) => !$department->isDeleted()
        );
        // reset keys to start from 0
        $nonDeletedDepartments = array_values($nonDeletedDepartments->toArray());

        // @improve T14508
        // you may want to change the return type, but if you do then adjust the callers as well
        // where necessary
        return collect($nonDeletedDepartments);
    }

    /**
     * @param array<int,DepartmentInterface> $departments
     */
    public function setDepartments($departments): self
    {
        $this->departments = new ArrayCollection($departments);

        return $this;
    }

    /**
     * Add department to this Organisation.
     */
    public function addDepartment(DepartmentInterface $department): self
    {
        if ($this->departments instanceof Collection) {
            if (!$this->departments->contains($department)) {
                $this->departments->add($department);
                $department->addOrga($this);
            }
        } else {
            $this->departments = new ArrayCollection([$department]);
            $department->addOrga($this);
        }

        return $this;
    }

    public function getProcedures(): ArrayCollection|Collection
    {
        return $this->procedures;
    }

    public function setProcedures($procedures): self
    {
        $this->procedures = $procedures;

        return $this;
    }

    public function getSubmissionType(): string
    {
        return $this->submissionType;
    }

    public function setSubmissionType(string $submissionType): self
    {
        $this->submissionType = $submissionType;

        return $this;
    }

    // @improve T12377
    /**
     * @return IlluminateCollection[User]
     */
    public function getAllUsersOfDepartments()
    {
        $users = collect([]);

        /** @var Department[] $departments */
        $departments = $this->getDepartments();
        foreach ($departments as $department) {
            $users = $users->merge($department->getUsers());
        }

        // in case of some users are not attached to a department of this organisation
        $users = $users->merge($this->getUsers());

        return $users->unique();
    }

    public function getAddressBookEntries(): Collection
    {
        return $this->addressBookEntries;
    }

    /**
     * @param AddressBookEntryInterface[] $addressBookEntries
     */
    public function setAddressBookEntries(array $addressBookEntries): self
    {
        $this->addressBookEntries = $addressBookEntries;

        return $this;
    }

    public function addAddressBookEntry(AddressBookEntryInterface $addressBookEntry): bool
    {
        if (!$this->addressBookEntries->contains($addressBookEntry)) {
            $addedAddressBookEntrySuccessful = $this->addressBookEntries->add($addressBookEntry);
            $addressBookEntry->setOrganisation($this);

            return $addedAddressBookEntrySuccessful;
        }

        return false;
    }

    public function removeAddressBookEntry(AddressBookEntryInterface $addressBookEntry): self
    {
        if ($this->addressBookEntries->contains($addressBookEntry)) {
            $addressBookEntry->setOrganisation(null);
            $this->addressBookEntries->removeElement($addressBookEntry);
        }

        return $this;
    }

    public function getLogo(): ?File
    {
        $branding = $this->getBranding();
        if ($branding instanceof Branding) {
            return $branding->getLogo();
        }

        return null;
    }

    public function setLogo(?FileInterface $logo): self
    {
        $branding = $this->getBranding();
        // If a branding relationship exists, just use it
        if ($branding instanceof Branding) {
            $branding->setLogo($logo);
        }
        // If a branding relationship does not exist, but a file is given,
        // create branding entity and add it as a relationship
        if (null === $branding && $logo instanceof File) {
            $branding = new Branding();
            $branding->setLogo($logo);
            $this->setBranding($branding);
        }

        return $this;
    }

    /**
     * @return Collection<int, Customer>
     */
    public function getCustomers()
    {
        $customers = new ArrayCollection();

        /** @var OrgaStatusInCustomer $statusInCustomer */
        foreach ($this->getStatusInCustomers() as $statusInCustomer) {
            $customer = $statusInCustomer->getCustomer();
            if (!$customers->contains($customer)) {
                $customers->add($customer);
            }
        }

        return $customers;
    }

    public function addCustomerAndOrgaType(
        CustomerInterface $customer,
        OrgaTypeInterface $orgaType,
        string $status = OrgaStatusInCustomer::STATUS_ACCEPTED,
    ): self {
        // create new
        $relation = new OrgaStatusInCustomer();
        $relation->setCustomer($customer);
        $relation->setOrgaType($orgaType);
        $relation->setOrga($this);
        $relation->setStatus($status);

        // add to list if not yet exists
        // Collection->contains does not find relation
        $exists = false;
        /** @var OrgaStatusInCustomer $item */
        foreach ($this->getStatusInCustomers() as $item) {
            if (
                $customer === $item->getCustomer()
                && $orgaType === $item->getOrgaType()
                && $this === $item->getOrga()
            ) {
                $exists = true;
            }
        }
        if (!$exists) {
            $relations = $this->getStatusInCustomers();
            $relations->add($relation);
            $this->setStatusInCustomers($relations);
        }

        return $this;
    }

    /**
     * @return Collection<int, OrgaStatusInCustomer>
     */
    public function getStatusInCustomers(): Collection
    {
        return $this->statusInCustomers;
    }

    /**
     * @param Collection<int, OrgaStatusInCustomerInterface> $statusInCustomers
     */
    public function setStatusInCustomers(Collection $statusInCustomers): self
    {
        $this->statusInCustomers = $statusInCustomers;

        return $this;
    }

    public function addStatusInCustomer(OrgaStatusInCustomerInterface $orgaStatusInCustomer): self
    {
        $this->statusInCustomers->add($orgaStatusInCustomer);

        return $this;
    }

    public function addCustomer(CustomerInterface $customer): bool
    {
        if (null === $this->customers) {
            $this->customers = new ArrayCollection();
        }
        if (!$this->customers->contains($customer)) {
            $this->customers[] = $customer;

            return true;
        }

        return false;
    }

    /**
     * @param CustomerInterface[] $customers
     */
    public function addCustomers(array $customers): self
    {
        foreach ($customers as $customer) {
            $this->addCustomer($customer);
        }

        return $this;
    }

    public function removeCustomer(CustomerInterface $customer): self
    {
        $this->customers->removeElement($customer);

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->getId();
    }

    public function isRegisteredInSubdomain($subdomain): bool
    {
        $customers = $this->getCustomers();
        /** @var Customer $customer */
        foreach ($customers as $customer) {
            if ($subdomain === $customer->getSubdomain()) {
                return true;
            }
        }

        return false;
    }

    public function getMainCustomer(): ?CustomerInterface
    {
        /** @var OrgaStatusInCustomer $customerOrgaTypes */
        foreach ($this->getStatusInCustomers() as $customerOrgaTypes) {
            $orgaType = $customerOrgaTypes->getOrgaType();
            $orgaTypeName = $orgaType->getName();
            if (OrgaType::MUNICIPALITY === $orgaTypeName && OrgaStatusInCustomer::STATUS_ACCEPTED === $customerOrgaTypes->getStatus()) {
                return $customerOrgaTypes->getCustomer();
            }
        }

        return null;
    }

    /**
     * @return Customer[]
     */
    public function getCustomersByActivationStatus(string $orgaTypeName, string $status): array
    {
        $customers = [];
        /** @var OrgaStatusInCustomer $statusInCustomer */
        foreach ($this->getStatusInCustomers() as $statusInCustomer) {
            $itemStatus = $statusInCustomer->getStatus();
            $itemOrgaTypeName = $statusInCustomer->getOrgaType()->getName();

            if ($itemOrgaTypeName === $orgaTypeName && $itemStatus === $status) {
                $customers[] = $statusInCustomer->getCustomer();
            }
        }

        return $customers;
    }

    public function getMasterUser(string $subdomain): ?User
    {
        $users = $this->getUsers();
        foreach ($users as $user) {
            if (Role::ORGANISATION_ADMINISTRATION === $user->getRoleBySubdomain($subdomain)) {
                return $user;
            }
        }

        return $users->count() > 0
            ? $this->getUsers()->first()
            : null;
    }

    public function getMasterToeb(): ?MasterToeb
    {
        return $this->masterToeb;
    }

    public function setMasterToeb(?MasterToebInterface $masterToeb): self
    {
        $this->masterToeb = $masterToeb;

        return $this;
    }

    public function hasType(string $orgaType, string $currentSubdomain): bool
    {
        return in_array($orgaType, $this->getTypes($currentSubdomain), true);
    }

    public function isDefaultCitizenOrganisation(): bool
    {
        return User::ANONYMOUS_USER_ORGA_ID === $this->id;
    }

    public function getBranding(): ?Branding
    {
        return $this->branding;
    }

    public function setBranding(?BrandingInterface $branding): self
    {
        $this->branding = $branding;

        return $this;
    }

    /**
     * @return Collection<int, InstitutionTag>
     */
    public function getAssignedTags(): Collection
    {
        return $this->assignedTags;
    }

    public function addAssignedTag(InstitutionTagInterface $tag): void
    {
        if (!$this->assignedTags->contains($tag)) {
            $this->assignedTags->add($tag);
            $tag->addTaggedInstitution($this);
        }
    }

    public function removeAssignedTag(InstitutionTagInterface $tag): void
    {
        if ($this->assignedTags->contains($tag)) {
            $this->assignedTags->removeElement($tag);
            $tag->getTaggedInstitutions()->removeElement($this);
        }
    }

    public function shouldBeIndexed(): bool
    {
        return !$this->deleted;
    }
}
