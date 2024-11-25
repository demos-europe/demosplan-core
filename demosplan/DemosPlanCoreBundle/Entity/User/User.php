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
use DemosEurope\DemosplanAddon\Contracts\Entities\AddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DepartmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SurveyVoteInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface as AddonUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserRoleInCustomerInterface;
use demosplan\DemosPlanCoreBundle\Constraint\RoleAllowedConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\UserWithMatchingDepartmentInOrgaConstraint;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use UnexpectedValueException;

use function in_array;

/**
 * @ORM\Table(
 *     name="_user",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="_u_gw_id", columns={"_u_gw_id"}),
 *
 *     @ORM\UniqueConstraint(name="_u_login", columns={"_u_login"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\UserRepository")
 *
 * @UserWithMatchingDepartmentInOrgaConstraint()
 */
class User implements AddonUserInterface, TotpTwoFactorInterface, EmailTwoFactorInterface
{
    public const HEARING_AUTHORITY_ROLES = [RoleInterface::HEARING_AUTHORITY_ADMIN, RoleInterface::HEARING_AUTHORITY_WORKER];
    public const PLANNING_AGENCY_ROLES = [RoleInterface::PLANNING_AGENCY_ADMIN, RoleInterface::PLANNING_AGENCY_WORKER];
    public const PUBLIC_AGENCY_ROLES = [RoleInterface::PUBLIC_AGENCY_COORDINATION, RoleInterface::PUBLIC_AGENCY_WORKER];
    /**
     * @var string|null
     *
     * @ORM\Column(name="_u_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var int
     *
     * @ORM\Column(name="_u_dm_id", type="integer", nullable=true)
     */
    protected $dmId;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_gender", type="string", length=6, nullable=true, options={"fixed":true})
     */
    protected $gender;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_title", type="string", length=45, nullable=true)
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_firstname", type="string", length=255, nullable=true)
     */
    protected $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_lastname", type="string", length=255, nullable=true)
     */
    protected $lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_email", type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_login", type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $login;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_u_password", type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $password;

    /**
     * This field should not exist. It is only (hopefully) temporary to understand
     * under which circumstances the $password field is cleared.
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $alternativeLoginPassword;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_u_salt", type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $salt;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_language", type="string", length=6, nullable=true, options={"fixed":true})
     */
    protected $language = 'de_DE';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_u_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_u_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastLogin;

    /**
     * @var bool
     *
     * @ORM\Column(name="_u_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_gw_id", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $gwId;

    /**
     * @var bool
     */
    protected $legacy;

    /**
     * @var array
     *
     * @ORM\Column(type="array", nullable=false)
     */
    protected $flags;

    /**
     * Get Newsletter.
     *
     * @var bool
     */
    protected $newsletter;

    /**
     * Is user new? atm quite the same as confirmed?
     *
     * @var bool
     */
    protected $newUser;

    /**
     * Are all mandatory fields filled out?
     *
     * @var bool
     */
    protected $profileCompleted;

    /**
     * Does the user want to get Notifications from forum changes.
     *
     * @var bool
     */
    protected $forumNotification;

    /**
     * Is user confirmed?
     *
     * @var bool
     */
    protected $accessConfirmed;

    /**
     * Is the user invited to participate?
     *
     * @var bool
     */
    protected $invited;

    /**
     * Is the user used within an intranet (like in fhh)?
     *
     * @var bool
     */
    protected $intranet;

    // @improve T15299
    /**
     * Diese Eigenschaft ist aus Legacygründen definiert, um das DB-Schema zu erhalten
     * $orga enthält die einzelne Organisation.
     *
     * @var Collection<int,OrgaInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", mappedBy="users", cascade={"persist"})
     */
    protected $orga;

    /**
     * Diese Eigenschaft ist aus Legacygründen definiert, um das DB-Schema zu erhalten
     * $department enthält die einzelne Abteilung.
     *
     * @var Collection<int, DepartmentInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department", mappedBy="users")
     */
    protected $departments;

    /**
     * Department des Users.
     *
     * @var DepartmentInterface
     */
    protected $department;

    /**
     * @var Collection<int, UserRoleInCustomerInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer", mappedBy="user", cascade={"persist", "remove"})
     */
    #[Assert\All([new Assert\NotNull(), new RoleAllowedConstraint()])]
    #[Assert\NotNull]
    protected $roleInCustomers;

    /**
     * @var Collection<int,AddressInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Address", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_user_address_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", onDelete="RESTRICT")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_a_id", referencedColumnName="_a_id", onDelete="RESTRICT")}
     * )
     */
    protected $addresses;

    /** @var CustomerInterface */
    protected $currentCustomer;

    /**
     * @var Collection<int, SurveyVoteInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote",
     *      mappedBy="user", cascade={"persist", "remove"})
     */
    protected $surveyVotes;

    /**
     * List of Role codes that are allowed in current project.
     *
     * @var array<int, string>
     */
    protected $rolesAllowed;

    /**
     * Reference to another User Entity that is combined with this user but represents the same
     * person in another context. Use case is that one "real world" user might act in different
     * organisations in dplan and may be able to switch between these organisations.
     * As one user might belong only to one organisation another "twin" user is needed to fulfill
     * this purpose.
     *
     * @var AddonUserInterface|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=true)
     */
    protected $twinUser;

    /**
     * The {@link ProcedureInterface} entities this user was manually authorized for.
     *
     * @var Collection<int, ProcedureInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="authorizedUsers")
     */
    protected $authorizedProcedures;

    /**
     * @var array|null
     */
    private $rolesArrayCache;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false,
     *      options={"comment":"Determines if this user is identified by external provider", "default": false})
     */
    private $providedByIdentityProvider = false;

    /**
     * Value used for two factor authentication via totp.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $totpSecret;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": false})
     */
    private bool $totpEnabled = false;

    /**
     * Value used for two factor authentication via email.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $authCode;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": false})
     */
    private bool $authCodeEmailEnabled = false;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->departments = new ArrayCollection();
        $this->flags = [];
        $this->orga = new ArrayCollection();
        $this->roleInCustomers = new ArrayCollection();
        $this->rolesAllowed = [];
        $this->surveyVotes = new ArrayCollection();
        $this->authorizedProcedures = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * I dont understand why, but doctrine need this Method with this name here.
     *
     * Reproducible with testSetAssigneeOfStatement():
     * (Neither the property "uId" nor one of the methods "getUId()", "uId()", "isUId()", "hasUId()", "__get()"
     * exist and have public access in class "Proxies\__CG__\demosplan\DemosPlanCoreBundle\Entity\User\User".)
     *
     * @return string
     */
    public function getUId()
    {
        return $this->getId();
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getDmId()
    {
        return $this->dmId;
    }

    /**
     * @param int $dmId
     */
    public function setDmId($dmId)
    {
        $this->dmId = $dmId;
    }

    /**
     * @return string
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * @param string $gender
     */
    public function setGender($gender)
    {
        $this->gender = $gender;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @return string
     */
    public function getFullname()
    {
        // In case of an empty string in firstname the trailing empty space gets trimmed.

        return trim($this->getFirstname().' '.$this->getLastname());
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getFullname();
    }

    /**
     * @deprecated use {@link User::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * @param string $firstname
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    /**
     * Get Email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @param string $lastname
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(?string $login): void
    {
        $this->login = $login;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->getUserIdentifier();
    }

    /**
     * Symfony > 6 needs getUserIdentifier() for auth system.
     */
    public function getUserIdentifier(): string
    {
        return $this->getLogin() ?? '';
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @deprecated $this->password should be safe now
     *
     * @return string|null
     */
    public function getAlternativeLoginPassword()
    {
        return $this->alternativeLoginPassword;
    }

    public function setAlternativeLoginPassword(?string $alternativeLoginPassword)
    {
        $this->alternativeLoginPassword = $alternativeLoginPassword;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     */
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt(?string $salt): AddonUserInterface
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    public function setCreatedDate(DateTimeInterface $createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * @param DateTime $modifiedDate
     */
    public function setModifiedDate(DateTimeInterface $modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;
    }

    /**
     * @param DateTime|DateTimeImmutable $lastLogin
     */
    public function setLastLogin(DateTimeInterface $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    /**
     * @return DateTime|DateTimeImmutable|null
     */
    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    /**
     * @return string
     */
    public function getGwId()
    {
        return $this->gwId;
    }

    /**
     * @param string $gwId
     */
    public function setGwId($gwId)
    {
        $this->gwId = $gwId;
    }

    /**
     * Ist das Passwort noch als md5 gespeichert?
     */
    public function isLegacy(): bool
    {
        return 32 === strlen($this->getPassword());
    }

    /**
     * @return array
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Do not use Matomo. Should be $useMatomo.
     */
    public function getNoPiwik(): bool
    {
        return $this->getFlagValue(UserFlagKey::NO_USER_TRACKING->value);
    }

    /**
     * @param bool $noPiwik
     */
    public function setNoPiwik($noPiwik)
    {
        // set Piwikflag
        $this->setFlagValue(UserFlagKey::NO_USER_TRACKING->value, $noPiwik);
    }

    public function getAssignedTaskNotification(): bool
    {
        return $this->getFlagValue(UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value);
    }

    public function setAssignedTaskNotification(bool $assignedTaskNotification)
    {
        $this->setFlagValue(UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value, $assignedTaskNotification);
    }

    public function getNewsletter(): bool
    {
        return $this->getFlagValue(UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value);
    }

    /**
     * @param bool $newsletter
     */
    public function setNewsletter($newsletter)
    {
        $this->setFlagValue(UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value, $newsletter);
    }

    public function isNewUser(): bool
    {
        return $this->getFlagValue(UserFlagKey::IS_NEW_USER->value);
    }

    /**
     * @param bool $newUser
     */
    public function setNewUser($newUser)
    {
        $this->setFlagValue(UserFlagKey::IS_NEW_USER->value, $newUser);
    }

    public function isIntranet(): bool
    {
        return $this->getFlagValue('intranet');
    }

    /**
     * @param bool $intranet
     */
    public function setIntranet($intranet)
    {
        $this->setFlagValue('intranet', $intranet);
    }

    public function isProfileCompleted(): bool
    {
        return $this->getFlagValue(UserFlagKey::PROFILE_COMPLETED->value);
    }

    /**
     * @param bool $profileCompleted
     */
    public function setProfileCompleted($profileCompleted)
    {
        $this->setFlagValue(UserFlagKey::PROFILE_COMPLETED->value, $profileCompleted);
    }

    /**
     * Is the user Guest or Citizen?
     */
    public function isPublicUser(): bool
    {
        return $this->hasAnyOfRoles([RoleInterface::CITIZEN, RoleInterface::GUEST]);
    }

    /**
     * Is the user Guest only?
     */
    public function isGuestOnly(): bool
    {
        return $this->hasRole(RoleInterface::GUEST) && 1 === count($this->getRoles());
    }

    public function isPlanner(): bool
    {
        $plannerRoles = [
            RoleInterface::PLANNING_AGENCY_ADMIN,
            RoleInterface::PLANNING_AGENCY_WORKER,
            RoleInterface::PRIVATE_PLANNING_AGENCY,
            RoleInterface::HEARING_AUTHORITY_ADMIN, // very similar to PLANNING_AGENCY_ADMIN (T27236#645613)
            RoleInterface::HEARING_AUTHORITY_WORKER, // very similar to PLANNING_AGENCY_WORKER (T27236#645613)
        ];

        return $this->hasAnyOfRoles($plannerRoles);
    }

    public function isHearingAuthority(?CustomerInterface $customer = null): bool
    {
        return $this->hasAnyOfRoles(self::HEARING_AUTHORITY_ROLES, $customer);
    }

    /**
     * Role::ORGANISATION_ADMINISTRATION is not included.
     */
    public function isProcedureAdmin(): bool
    {
        return $this->hasAnyOfRoles([RoleInterface::HEARING_AUTHORITY_ADMIN, RoleInterface::PLANNING_AGENCY_ADMIN]);
    }

    public function isPlanningAgency(?CustomerInterface $customer = null): bool
    {
        return $this->hasAnyOfRoles(self::PLANNING_AGENCY_ROLES, $customer);
    }

    public function isPublicAgency(): bool
    {
        return $this->hasAnyOfRoles(self::PUBLIC_AGENCY_ROLES);
    }

    /**
     * Is the user Citizen?
     */
    public function isCitizen(): bool
    {
        return $this->hasRole(RoleInterface::CITIZEN);
    }

    public function getForumNotification(): bool
    {
        return $this->getFlagValue(UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value);
    }

    /**
     * @param bool $forumNotification
     */
    public function setForumNotification($forumNotification)
    {
        $this->setFlagValue(UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value, $forumNotification);
    }

    public function isAccessConfirmed(): bool
    {
        return $this->getFlagValue(UserFlagKey::ACCESS_CONFIRMED->value);
    }

    /**
     * @param bool $accessConfirmed
     */
    public function setAccessConfirmed($accessConfirmed)
    {
        $this->setFlagValue(UserFlagKey::ACCESS_CONFIRMED->value, $accessConfirmed);
    }

    public function isInvited(): bool
    {
        return $this->getFlagValue(UserFlagKey::INVITED->value);
    }

    /**
     * @param bool $invited
     */
    public function setInvited($invited)
    {
        $this->setFlagValue(UserFlagKey::INVITED->value, $invited);
    }

    /**
     * Add arbitrary UserFlag.
     *
     * @param string $key
     * @param bool   $value
     */
    public function addFlag($key, $value)
    {
        $this->setFlagValue($key, $value);
    }

    /**
     * Return arbitrary UserFlag.
     *
     * @param string $key
     */
    public function getFlag($key): bool
    {
        return $this->getFlagValue($key);
    }

    public function addAuthorizedProcedure(ProcedureInterface $procedure): bool
    {
        $alreadyPresent = $this->authorizedProcedures->contains($procedure);
        if (!$alreadyPresent) {
            $this->authorizedProcedures->add($procedure);
        }

        return !$alreadyPresent;
    }

    /**
     * @return Collection<int, ProcedureInterface>
     */
    public function getAuthorizedProcedures(): Collection
    {
        return $this->authorizedProcedures;
    }

    /**
     * Setzt eine Userflag. Wenn nicht vorhanden, wird sie neu generiert.
     *
     * @param string $flagKey
     */
    protected function setFlagValue($flagKey, $flagValue)
    {
        $this->flags[$flagKey] = $flagValue;
    }

    /**
     * get UserFlag by Key.
     *
     * @param string $flagKey
     */
    protected function getFlagValue($flagKey): bool
    {
        if (is_array($this->flags) && array_key_exists($flagKey, $this->flags)) {
            return (bool) $this->flags[$flagKey];
        }

        return false;
    }

    /**
     * Organisation des Users.
     */
    public function getOrga(): ?OrgaInterface
    {
        if ($this->orga instanceof Collection && 0 < $this->orga->count()) {
            return $this->orga->first();
        }

        return null;
    }

    /**
     * Returns the Id of the organisation where this user belongs to, if exists, otherwise null.
     *
     * @return string|null
     */
    public function getOrganisationId()
    {
        return null === $this->getOrga() ? null : $this->getOrga()->getId();
    }

    /**
     * Returns the name of the organisation where this user belongs to, if exists, otherwise null.
     *
     * @return string|null
     */
    public function getOrgaName()
    {
        return is_null($this->getOrga()) ? null : $this->getOrga()->getName();
    }

    /**
     * Legacyfunction to provide OrgaName to be safer on refactoring.
     */
    public function getOrganisationNameLegal(): ?string
    {
        if ($this->getOrga() instanceof OrgaInterface) {
            return $this->getOrga()->getNameLegal();
        }

        return '';
    }

    /**
     * Organisation des Users hinzufügen.
     */
    public function setOrga(OrgaInterface $orga)
    {
        $this->orga = new ArrayCollection([$orga]);
    }

    /**
     * Organisation des Users entfernen.
     */
    public function unsetOrgas()
    {
        $this->orga = new ArrayCollection([]);
    }

    /**
     * Department des Users.
     *
     * @return DepartmentInterface|null
     */
    public function getDepartment()
    {
        if ($this->department instanceof DepartmentInterface) {
            return $this->department;
        }
        if ($this->departments instanceof Collection && 1 == $this->departments->count()) {
            $this->department = $this->departments->first();

            return $this->department;
        }

        return null;
    }

    public function getDepartmentId(): string
    {
        if ($this->getDepartment() instanceof DepartmentInterface) {
            return $this->getDepartment()->getId();
        }

        return '';
    }

    /**
     * Legacyfunction to provide DepartmentName to be safer on refactoring.
     *
     * @return string
     */
    public function getDepartmentNameLegal()
    {
        return $this->getDepartmentName();
    }

    public function getDepartmentName(): string
    {
        if ($this->getDepartment() instanceof DepartmentInterface) {
            return $this->getDepartment()->getName();
        }

        return '';
    }

    /**
     * Department des Users.
     *
     * @return Collection<int, DepartmentInterface>
     */
    public function getDepartments()
    {
        return $this->departments;
    }

    /**
     * Add Department to User.
     */
    public function addDepartment(DepartmentInterface $department)
    {
        if ($this->departments instanceof Collection && !$this->departments->contains($department)) {
            $this->departments->add($department);
        } else {
            $this->departments = new ArrayCollection([$department]);
        }
    }

    /**
     * Replace a Department of User.
     */
    public function setDepartment(DepartmentInterface $department)
    {
        $this->departments = new ArrayCollection([$department]);
        $this->department = $department;
    }

    /**
     * Remove Department from User.
     */
    public function removeDepartment(DepartmentInterface $department)
    {
        if ($this->departments instanceof Collection && $this->departments->contains($department)) {
            $this->departments->removeElement($department);
        }
    }

    /**
     * Unset Department.
     */
    public function unsetDepartment()
    {
        $this->department = null;
    }

    public function getAddress(): ?AddressInterface
    {
        if ($this->addresses instanceof Collection) {
            $firstAddress = $this->addresses->first();
            if ($firstAddress instanceof AddressInterface) {
                return $firstAddress;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, AddressInterface>
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
        $this->addresses->clear();
        $this->addresses = new ArrayCollection($addresses);

        return $this;
    }

    /**
     * Add Address to User.
     *
     * @param AddressInterface $address
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
     * Get Postalcode from associated (first) {@link AddressInterface}.
     */
    public function getPostalcode(): string
    {
        $firstAddress = $this->getAddress();

        return null === $firstAddress ? '' : $firstAddress->getPostalcode();
    }

    /**
     * @param string $postalcode
     *
     * @return $this
     */
    public function setPostalcode($postalcode)
    {
        $this->setAddressValue('postalcode', $postalcode);

        return $this;
    }

    /**
     * Get City from associated (first) {@link AddressInterface}.
     */
    public function getCity(): string
    {
        $firstAddress = $this->getAddress();

        return null === $firstAddress ? '' : $firstAddress->getCity();
    }

    /**
     * @param string $city
     *
     * @return $this
     */
    public function setCity($city)
    {
        $this->setAddressValue('city', $city);

        return $this;
    }

    /**
     * Get Street from associated (first) {@link AddressInterface}.
     */
    public function getStreet(): string
    {
        $firstAddress = $this->getAddress();

        return null === $firstAddress ? '' : $firstAddress->getStreet();
    }

    /**
     * Get Housenumber from associated (first) {@link AddressInterface}.
     */
    public function getHouseNumber(): string
    {
        $firstAddress = $this->getAddress();

        return null === $firstAddress ? '' : $firstAddress->getHouseNumber();
    }

    /**
     * @return $this
     */
    public function setHouseNumber(string $houseNumber)
    {
        $this->setAddressValue('houseNumber', $houseNumber);

        return $this;
    }

    /**
     * @param string $street
     *
     * @return $this
     */
    public function setStreet($street)
    {
        $this->setAddressValue('street', $street);

        return $this;
    }

    /**
     * Get State from associated (first) {@link AddressInterface}.
     */
    public function getState(): ?string
    {
        $firstAddress = $this->getAddress();

        return null === $firstAddress ? '' : $firstAddress->getState();
    }

    /**
     * @param string $state
     *
     * @return $this
     */
    public function setState($state)
    {
        $this->setAddressValue('state', $state);

        return $this;
    }

    /**
     * Save single Value in associated Address.
     *
     * @param string $key
     * @param string $value
     */
    public function setAddressValue($key, $value)
    {
        $method = 'set'.ucfirst($key);
        $address = $this->addresses->first();
        if (!$address instanceof AddressInterface) {
            $address = new Address();
        }
        if (method_exists($address, $method)) {
            $address->$method($value);
            $this->setAddresses([$address]);
        }
    }

    /**
     * @param array<int, string> $roles
     */
    public function setRolesAllowed($roles): void
    {
        $this->rolesAllowed = $roles;
    }

    public function getTwinUser(): ?AddonUserInterface
    {
        return $this->twinUser;
    }

    public function hasTwinUser(): bool
    {
        return null !== $this->twinUser;
    }

    public function setTwinUser(?AddonUserInterface $twinUser): AddonUserInterface
    {
        $this->twinUser = $twinUser;

        return $this;
    }

    /**
     * Returns collection of roles the user has with a specified customer (current is default).
     *
     * @return Collection<int, RoleInterface>
     */
    public function getDplanroles(?CustomerInterface $customer = null): Collection
    {
        $roles = new ArrayCollection();
        $relations = $this->roleInCustomers->toArray();

        // get customer to check
        if (!$customer instanceof CustomerInterface) {
            $specifiedCustomerId = $this->currentCustomer instanceof Customer ? $this->currentCustomer->getId() : '';
        } else {
            $specifiedCustomerId = $customer->getId();
        }

        /** @var UserRoleInCustomerInterface $relation */
        foreach ($relations as $relation) {
            $relationCustomerId = $relation->getCustomer() instanceof CustomerInterface ? $relation->getCustomer()->getId() : '';
            if ($specifiedCustomerId === $relationCustomerId
                && !$roles->contains($relation->getRole())
                && in_array($relation->getRole()->getCode(), (array) $this->rolesAllowed, true)) {
                $roles->add($relation->getRole());
            }
        }

        return $roles;
    }

    /**
     * Returns an array of the code of roles the user has with a specified customer (current is default).
     *
     * @return string[]
     */
    public function getDplanRolesArray(?CustomerInterface $customer = null): array
    {
        if ($this->hasInvalidRoleCache()) {
            $this->rolesArrayCache = [];
            $customer ??= $this->getCurrentCustomer();
            /** @var RoleInterface $role */
            foreach ($this->getDplanroles($customer) as $role) {
                $this->rolesArrayCache[] = $role->getCode();
            }
        }

        return $this->rolesArrayCache;
    }

    /**
     * This function is needed to clear the roles cache to prevent roles being still present
     * after they have been actually removed.
     */
    public function clearRolesCache(): void
    {
        $this->rolesArrayCache = [];
    }

    /**
     * @return string[]
     */
    public function getDplanRoleGroupsArray()
    {
        $rolesArray = [];
        /** @var RoleInterface $role */
        foreach ($this->getDplanroles() as $role) {
            $rolesArray[] = $role->getGroupCode();
        }

        return $rolesArray;
    }

    /**
     * Alias for getDplanRoleGroupsArray, used e.g. in twig.
     *
     * @return string[]
     */
    public function getRoleGroups()
    {
        return $this->getDplanRoleGroupsArray();
    }

    /**
     * @return string|null
     */
    public function getDplanRolesString()
    {
        if (!$this->getDplanroles() instanceof Collection) {
            return null;
        }
        $rolesArray = [];
        foreach ($this->getDplanroles() as $role) {
            /* @var RoleInterface $role */
            $rolesArray[] = $role->getCode();
        }

        return implode(',', $rolesArray);
    }

    /**
     * @return string|null
     */
    public function getDplanRolesGroupString()
    {
        if (!$this->getDplanroles() instanceof Collection) {
            return null;
        }
        $rolesArray = [];
        foreach ($this->getDplanroles() as $role) {
            /* @var RoleInterface $role */
            $rolesArray[] = $role->getGroupCode();
        }
        $rolesArray = array_unique($rolesArray);

        return implode(',', $rolesArray);
    }

    /**
     * Sets Roles. Note that, in order to do this, {@link UserRoleInCustomerInterface} objects are created which have a role and
     * the current customer.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/ Wiki: Permissions, Roles, etc.
     *
     * @param RoleInterface[]        $roles
     * @param CustomerInterface|null $customer
     */
    public function setDplanroles(array $roles, $customer = null): void
    {
        foreach ($roles as $role) {
            $this->addDplanrole($role, $customer);
        }
    }

    /**
     * @param Collection<int,UserRoleInCustomerInterface> $roleInCustomers
     */
    public function setRoleInCustomers(Collection $roleInCustomers): void
    {
        $this->roleInCustomers = $roleInCustomers;
    }

    /**
     * @param CustomerInterface|null $customer
     */
    public function addDplanrole(RoleInterface $role, $customer = null)
    {
        // prevents the same role being set multiple times (if they have been set previously)
        if ($this->hasRole($role->getCode(), $customer)) {
            return;
        }

        $customer ??= $this->getCurrentCustomer();

        $userRoleInCustomer = new UserRoleInCustomer();
        $userRoleInCustomer->setUser($this);
        $userRoleInCustomer->setRole($role);
        $userRoleInCustomer->setCustomer($customer);

        $this->roleInCustomers->add($userRoleInCustomer);
    }

    /**
     * Check whether user has role (code) with a specified customer (current is default).
     *
     * @param string $role
     */
    public function hasRole($role, ?CustomerInterface $customer = null): bool
    {
        $customer ??= $this->getCurrentCustomer();

        return in_array($role, $this->getDplanRolesArray($customer));
    }

    public function hasAnyOfRoles(array $roles, ?CustomerInterface $customer = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $customer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return string[] The user roles
     */
    public function getRoles(): array
    {
        return $this->getDplanRolesArray();
    }

    /**
     * Alias for getDplanRolesString.
     *
     * @return string
     */
    public function getRole()
    {
        return $this->getDplanRolesString();
    }

    /**
     * Setzen des loggedIn Status auf true.
     */
    public function isLoggedIn(): bool
    {
        return !$this instanceof AnonymousUser;
    }

    /**
     * We cannot remove any sensitive data from user object during login (this is where this is called)
     * as user object is persisted later on which would lead in an empty password field in database.
     */
    public function eraseCredentials(): void
    {
    }

    public function getEntityContentChangeIdentifier(): string
    {
        return $this->getFullname();
    }

    public function getDraftStatementSubmissionReminderEnabled(): bool
    {
        return $this->getFlagValue(UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value);
    }

    public function setDraftStatementSubmissionReminderEnabled(bool $draftStatementSubmissionReminderEnabled)
    {
        $this->setFlagValue(UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value, $draftStatementSubmissionReminderEnabled);
    }

    /**
     * Retrieve all customers that the user is associated with in any way.
     *
     * @return array<int, CustomerInterface>
     */
    public function getCustomers(): array
    {
        return $this->roleInCustomers
            ->map(static fn (UserRoleInCustomerInterface $roleInCustomer) => $roleInCustomer->getCustomer())->toArray();
    }

    public function isConnectedToCustomerId(string $customerId): bool
    {
        return $this->roleInCustomers
            ->map(static fn (UserRoleInCustomerInterface $roleInCustomer): ?string => $roleInCustomer->getCustomer()?->getId())
            ->contains($customerId);
    }

    /**
     * Retrieve all customers that the user is associated with directly. This is only the case for customer users.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/ wiki: customer role user orga logic
     */
    public function getCustomersForCustomerUsers(): array
    {
        $customers = [];
        $relations = $this->roleInCustomers->toArray();
        /** @var UserRoleInCustomerInterface $relation */
        foreach ($relations as $relation) {
            $relation->getRole();
            $customers[] = $relation->getCustomer();
        }

        return $customers;
    }

    /**
     * Retrieve all customers that the user is associated with through an orga.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/ wiki: customer role user orga logic
     *
     * @param array $orgaType Per default, all are returned. Array of orgaType constants.
     */
    public function getCustomersForUsersOfOrganisationsOfType(array $orgaType = [OrgaTypeInterface::MUNICIPALITY, OrgaTypeInterface::PLANNING_AGENCY, OrgaTypeInterface::PUBLIC_AGENCY]): array
    {
        if ([] === $orgaType) {
            throw new UnexpectedValueException('Invalid OrgaType set.');
        }

        // first, get all orgas
        $customers = [];
        $orgas = $this->orga->toArray();
        /** @var OrgaInterface $orga */
        foreach ($orgas as $orga) {
            // each orga has customers. get all of them.
            /** @var OrgaStatusInCustomerInterface $orgaStatusInCustomer */
            $orgaStatusInCustomers = $orga->getStatusInCustomers();

            foreach ($orgaStatusInCustomers as $orgaStatusInCustomer) {
                // now, filter for those of the desired orgaType(s)
                if (in_array($orgaStatusInCustomer->getOrgaType()->getName(), $orgaType, true)) {
                    $customers[] = $orgaStatusInCustomer->getCustomer();
                }
            }
        }

        // array unique since the user can be part of several orgas that have the same customer.
        // array values to restore numerical index
        return array_values(array_unique($customers));
    }

    /**
     * The user has an organization that, in its role as "Kommmune", can only have one customer. Get that customer.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/ wiki: customer role user orga logic
     *
     * @return CustomerInterface|null
     */
    public function getCustomersForUsersOfOrganisationsOfTypeKommune()
    {
        $array = $this->getCustomersForUsersOfOrganisationsOfType([OrgaTypeInterface::MUNICIPALITY]);

        // since, per definition, there can only be one, the first element is always the right one
        if (1 === count($array) && isset($array[0])) {
            return $array[0];
        }

        $message = 'Current user has no or more than one organization (1. possible source of error) that is '.
            'a Kommune (2. possible source of error) and has a Customer (3. possible source of error).';
        throw new UnexpectedValueException($message);
    }

    /**
     * Returns an array of Customer ids.
     */
    public function getCustomersIds(): array
    {
        $ids = [];
        $customers = $this->getCustomers();
        foreach ($customers as $customer) {
            if ($customer instanceof CustomerInterface) {
                $ids[] = $customer->getId();
            }
        }

        return $ids;
    }

    /**
     * @return Collection<int,UserRoleInCustomerInterface>
     */
    public function getRoleInCustomers(): Collection
    {
        return $this->roleInCustomers;
    }

    /**
     * May be null e.g. during user add.
     */
    public function getCurrentCustomer(): ?CustomerInterface
    {
        return $this->currentCustomer;
    }

    public function setCurrentCustomer(CustomerInterface $currentCustomer): void
    {
        $this->currentCustomer = $currentCustomer;
    }

    /**
     * Given a subdomain returns the role that current user plays there.
     * If user doesn't belong to subdomain, then empty string is returned.
     *
     * @return string
     */
    public function getRoleBySubdomain(string $subdomain)
    {
        $rolesInCustomers = $this->getRoleInCustomers();
        /** @var UserRoleInCustomerInterface $roleInCustomer */
        foreach ($rolesInCustomers as $roleInCustomer) {
            $customer = $roleInCustomer->getCustomer();
            if ($customer instanceof CustomerInterface && $customer->getSubdomain() === $subdomain) {
                return $roleInCustomer->getRole()->getCode();
            }
        }

        return '';
    }

    /**
     * @return Collection<int, SurveyVoteInterface>
     */
    public function getSurveyVotes(): Collection
    {
        return $this->surveyVotes;
    }

    /**
     * Returns SurveyVote with the given id or null if none exists.
     *
     * @param string $surveyVoteId
     */
    public function getSurveyVote($surveyVoteId): ?SurveyVoteInterface
    {
        /** @var SurveyVoteInterface $surveyVote */
        foreach ($this->surveyVotes as $surveyVote) {
            if ($surveyVote->getId() === $surveyVoteId) {
                return $surveyVote;
            }
        }

        return null;
    }

    public function addSurveyVote(SurveyVoteInterface $surveyVote): void
    {
        $this->surveyVotes[] = $surveyVote;
    }

    /**
     * Should be indexed in Elasticsearch.
     */
    public function shouldBeIndexed(): bool
    {
        return !$this->deleted;
    }

    public function isDefaultGuestUser(): bool
    {
        return AddonUserInterface::ANONYMOUS_USER_ID === $this->id;
    }

    public function isProvidedByIdentityProvider(): bool
    {
        return $this->providedByIdentityProvider;
    }

    public function setProvidedByIdentityProvider(bool $providedByIdentityProvider): void
    {
        $this->providedByIdentityProvider = $providedByIdentityProvider;
    }

    private function hasInvalidRoleCache(): bool
    {
        return null === $this->rolesArrayCache || count($this->rolesArrayCache) !== $this->roleInCustomers->count();
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTotpEnabled();
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        // period and digits are chosen to be compatible with Google Authenticator
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function isTotpEnabled(): bool
    {
        return $this->totpEnabled;
    }

    public function setTotpEnabled(bool $totpEnabled): void
    {
        $this->totpEnabled = $totpEnabled;
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->authCodeEmailEnabled;
    }

    public function setAuthCodeEmailEnabled(bool $authCodeEmailEnabled): void
    {
        $this->authCodeEmailEnabled = $authCodeEmailEnabled;
    }

    public function getEmailAuthRecipient(): string
    {
        return $this->getEmail();
    }

    public function getEmailAuthCode(): string
    {
        return $this->authCode ?? '';
    }

    public function setEmailAuthCode(string $authCode): void
    {
        $this->authCode = $authCode;
    }
}
