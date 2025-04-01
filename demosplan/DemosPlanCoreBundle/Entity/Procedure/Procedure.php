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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\EmailAddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ExportFieldsConfigurationInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\NotificationReceiverInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureBehaviorDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureSettingsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureUiDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFormDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SurveyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Constraint\ProcedureAllowedSegmentsConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\ProcedureMasterTemplateConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\ProcedureTemplateConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\ProcedureTypeConstraint;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\SluggedEntity;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_procedure")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository")
 *
 * @ORM\AssociationOverrides({
 *
 *      @ORM\AssociationOverride(name="slugs",
 *          joinTable=@ORM\JoinTable(
 *              joinColumns=@ORM\JoinColumn(name="p_id", referencedColumnName="_p_id"),
 *              inverseJoinColumns=@ORM\JoinColumn(name="s_id", referencedColumnName="id")
 *          )
 *      )
 * })
 *
 * @ProcedureTemplateConstraint(groups={ProcedureInterface::VALIDATION_GROUP_MANDATORY_PROCEDURE_TEMPLATE})
 *
 * @ProcedureTypeConstraint(groups={ProcedureInterface::VALIDATION_GROUP_MANDATORY_PROCEDURE_ALL_INCLUDED})
 *
 * @ProcedureMasterTemplateConstraint(groups={ProcedureInterface::VALIDATION_GROUP_MANDATORY_PROCEDURE})
 *
 * @ProcedureAllowedSegmentsConstraint(groups={ProcedureInterface::VALIDATION_GROUP_MANDATORY_PROCEDURE})
 */
class Procedure extends SluggedEntity implements ProcedureInterface
{
    /**
     * @var string|null
     *                  Generates a UUID in code that confirms to https://www.w3.org/TR/1999/REC-xml-names-19990114/#NT-NCName
     *                  to be able to be used as xs:ID type in XML messages
     *
     * @ORM\Column(name="_p_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\NCNameGenerator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_name", type="text", length=65535, nullable=false)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_short_url", type="string", length=256, nullable=false, options={"default":""})
     */
    protected $shortUrl = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_o_name", type="string", length=255, nullable=false)
     */
    protected $orgaName = '';

    /**
     * Die eigentliche Entity liegt unter $this->orga.
     *
     * @var string
     */
    protected $orgaId;

    /**
     * {@link Orga} that owns the procedure. Should never be null, as all procedures are created by some
     * organization. Must never be {@link UserInterface::ANONYMOUS_USER_ORGA_ID}.
     * Will be null on some ancient procedures.
     *
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", inversedBy="procedures")
     *
     * @ORM\JoinColumns({
     *
     *   @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="RESTRICT")
     * })
     */
    protected $orga;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_desc", type="text", length=65535, nullable=false)
     */
    protected $desc = '';

    /**
     * @ORM\OneToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhase",
     *     cascade={"persist", "remove"}
     * )
     *
     * @ORM\JoinColumn(nullable=false)
     */
    protected ProcedurePhase $phase;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_logo", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $logo = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_p_extern_id", type="string", length=25, nullable=false, options={"fixed":true})
     */
    protected $externId = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_p_plis_id", type="string", length=36, options={"fixed":true, "default":""}, nullable=false)
     */
    protected $plisId = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_p_closed", type="boolean", nullable=false, options={"default":false})
     */
    protected $closed = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_p_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    // improve: use blueprint/template instead of master
    /**
     * `true`/`1` if this instance is not an actual procedure but a procedure template instead.
     * `false`/`0` otherwise.
     *
     * @var int|bool
     *
     * @ORM\Column(name="_p_master", type="integer", nullable=false)
     */
    protected $master = false;

    /**
     * `true` if this procedure-template instance is the main master template of all procedure-templates.
     * `false` otherwise.
     *
     * Actual procedures must never have this property set to `true`.
     *
     * There must be only one instance with this property set to `true`.
     *
     * @var bool
     *
     * @ORM\Column(name="master_template", type="boolean", nullable=false)
     */
    protected $masterTemplate = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_external_name", type="text", length=65535, nullable=false)
     */
    protected $externalName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_p_external_desc", type="text", length=65535, nullable=false)
     */
    protected $externalDesc = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_p_public_participation", type="boolean", nullable=false, options={"default":false})
     */
    protected $publicParticipation = false;

    /**
     * @ORM\OneToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhase",
     *     cascade={"persist", "remove"}
     * )
     *
     * @ORM\JoinColumn(nullable=false)
     */
    protected ProcedurePhase $publicParticipationPhase;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_public_participation_contact", type="string", length=2048, nullable=false)
     */
    protected $publicParticipationContact = '';

    /**
     * Enable publication of statements, individual statements must be explicitly published.
     *
     * @var bool
     *
     * @ORM\Column(name="_p_public_participation_publication_enabled", type="boolean", nullable=false, options={"default":true})
     */
    protected $publicParticipationPublicationEnabled = true;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_location_name", type="string", length=1024, nullable=false)
     */
    protected $locationName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_p_location_postcode", type="string", length=5, nullable=false, options={"default":""})
     */
    protected $locationPostCode = '';

    /**
     * Virtuelle Eigenschaft aus den Settings, vereinfacht das Indizieren in Elasticsearch.
     *
     * @var string
     */
    protected $coordinate;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_municipal_code", type="string", length=10, nullable=false)
     */
    protected $municipalCode = '';

    /**
     * Amtlicher Regionalschlüssel.
     *
     * @see https://de.wikipedia.org/wiki/Amtlicher_Gemeindeschl%C3%BCssel
     *
     * @var string
     *
     * @ORM\Column(name="_p_ars", type="string", length=12, nullable=false,
     *     options={"comment":"Amtlicher Regionalschluessel", "default": ""})
     */
    protected $ars = '';

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_p_created_date", type="datetime", nullable=false)
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_p_closed_date", type="datetime", nullable=false)
     */
    protected $closedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_p_deleted_date", type="datetime", nullable=false)
     */
    protected $deletedDate;

    /**
     * Invited organisations.
     *
     * @var Collection<int, Orga>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", inversedBy="procedureInvitations")
     *
     * @ORM\JoinTable(
     *     name="_procedure_orga_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="CASCADE")}
     * )
     */
    protected $organisation;

    /**
     * Elasticsearch benötigt die Ids der beteiligten Orgas flat.
     *
     * @var string[]
     */
    protected $organisationIds;

    /**
     * PlanningAgencies that are allowed to administrate procedure.
     *
     * @var Collection<int, Orga>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", inversedBy="administratableProcedures")
     *
     * @ORM\JoinTable(
     *     name="procedure_planningoffices",
     *     joinColumns={@ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id")}
     * )
     */
    protected $planningOffices;

    /**
     * @var Collection<int, Orga>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinTable(
     *     name="procedure_orga_datainput",
     *     joinColumns={@ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="CASCADE")}
     * )
     */
    protected $dataInputOrganisations;

    /**
     * @var ProcedureSettings
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings", mappedBy="procedure", cascade={"persist", "remove"})
     */
    protected $settings;

    /**
     * @var Collection<int, TagTopic>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic", mappedBy="procedure", cascade={"remove"})
     */
    protected $topics;

    /**
     * @var Collection<int, NotificationReceiver>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver", mappedBy="procedure", cascade={"persist", "remove"})
     */
    protected $notificationReceivers;

    /**
     * @var Collection<int,Elements>
     *
     * @ORM\OneToMany(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Document\Elements", mappedBy="procedure")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id")
     */
    protected $elements;

    /**
     * Custom list of Users, to access this Procedure, created by User.
     *
     * @var Collection<int, User>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User", inversedBy="authorizedProcedures")
     *
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="procedure_id", referencedColumnName="_p_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", onDelete="CASCADE")}
     * )
     */
    protected $authorizedUsers;

    /**
     * Must be provided, hence should not be nullable. However the prod database may contain null values
     * which need to be considered and result in nullable=true.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=364, nullable=true, options={"comment":"main email address of the agency (organization) assigned to this procedure"})
     */
    protected $agencyMainEmailAddress;

    /**
     * Email addresses to use as CC when sending an email to the agencyMainEmailAddress.
     *
     * @var Collection<int, EmailAddress>
     *
     * @ORM\ManyToMany(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\EmailAddress",
     *     cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="procedure_agency_extra_email_address",
     *     joinColumns={@ORM\JoinColumn(name="procedure_id", referencedColumnName="_p_id", nullable=false)},
     *     inverseJoinColumns={@ORM\JoinColumn(name="email_address_id", referencedColumnName="id", nullable=false)})
     */
    protected $agencyExtraEmailAddresses;

    /**
     * T15644:.
     *
     * @var Customer
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="customer", referencedColumnName="_c_id", nullable=true)
     */
    protected $customer;

    /**
     * @var Collection<int, ProcedureCategory>
     *
     * @ORM\ManyToMany(
     *      targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCategory",
     *      cascade={"persist"}
     * )
     *
     * @ORM\JoinTable(
     *      name="procedure_procedure_category_doctrine",
     *      joinColumns={@ORM\JoinColumn(
     *          name="procedure_id",
     *          referencedColumnName="_p_id",
     *          nullable=false,
     *          onDelete="CASCADE"
     *      )},
     *      inverseJoinColumns={@ORM\JoinColumn(
     *          name="procedure_category_id",
     *          referencedColumnName="procedure_category_id",
     *          nullable=false,
     *          onDelete="CASCADE"
     *      )}
     * )
     */
    protected $procedureCategories;

    /**
     * @var Collection<int, Statement>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="procedure", cascade={"persist", "remove"})
     */
    protected $statements;

    /**
     * @var Collection<int, Survey>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Survey\Survey",
     *      mappedBy="procedure", cascade={"persist", "remove"})
     */
    protected $surveys;

    /**
     * Defined as nullable=true, because of Procedure-Blueprints will not have a related ProcedureType.
     *
     * @var ProcedureType|null
     *
     * Many procedureTypes have one procedure. This is the owning side.
     * (In Doctrine Many have to be the owning side in a ManyToOne relationship.)
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType", inversedBy="procedures")
     *
     * @ORM\JoinColumn(nullable=true)
     */
    private $procedureType;

    /**
     * Defined as nullable=true, because of Procedure-Blueprints will not have a related StatementFormDefinition.
     *
     * @var StatementFormDefinition|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition", inversedBy="procedure", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(nullable=true)
     */
    private $statementFormDefinition;

    /**
     * Defined as nullable=true, because of Procedure-Blueprints will not have a related ProcedureBehaviorDefinition.
     *
     * @var ProcedureBehaviorDefinition|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition", inversedBy="procedure", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(nullable=true)
     */
    private $procedureBehaviorDefinition;

    /**
     * Defined as nullable=true, because of Procedure-Blueprints will not have a related ProcedureUiDefinition.
     *
     * @var ProcedureUiDefinition|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition", inversedBy="procedure", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(nullable=true)
     */
    private $procedureUiDefinition;

    /**
     * Definition of Fields to be rendered/added in Export of this Procedure.
     *
     * @var Collection<int, ExportFieldsConfiguration>
     *
     * @ORM\OneToMany(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration", mappedBy="procedure", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    private $exportFieldsConfigurations;

    /**
     * Any files referenced to this procedure.
     *
     * @var Collection<int, File>
     *
     * @ORM\OneToMany(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\File", mappedBy="procedure", cascade={"remove"})
     */
    private $files;

    // @improve T26104
    /**
     * This property holds a reference to any external Id that may be applicable to this procedure. This may be
     * e.g. an Id given by an external system that we need to reference later on.
     *
     * Please note that the property is not named like the database field. This is by purpose. There already exists a
     * {@see ProcedureInterface::externId} property. This one seems to be outdated and may be deleted later on, but this needs
     * some more investigation. As soon as the other field is dropped, this property might be renamed to externId
     *
     * @var string
     *
     * @ORM\Column(name="extern_id", type="string", length=50, nullable=false, options={"default":""})
     */
    private $xtaPlanId = '';

    /**
     * @var Collection<int, Place>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Workflow\Place", mappedBy="procedure", cascade={"persist"})
     */
    private $segmentPlaces;

    protected CustomFieldConfiguration $customFieldConfiguration;

    public function __construct()
    {
        $this->organisation = new ArrayCollection();
        $this->elements = new ArrayCollection();
        $this->topics = new ArrayCollection();
        $this->closedDate = new DateTime();
        $this->deletedDate = new DateTime();
        $this->dataInputOrganisations = new ArrayCollection();
        $this->authorizedUsers = new ArrayCollection();
        $this->agencyExtraEmailAddresses = new ArrayCollection();
        $this->slugs = new ArrayCollection();
        $this->planningOffices = new ArrayCollection();
        $this->procedureCategories = new ArrayCollection();
        $this->statements = new ArrayCollection();
        $this->surveys = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->notificationReceivers = new ArrayCollection();
        $this->exportFieldsConfigurations = new ArrayCollection();
        $this->segmentPlaces = new ArrayCollection();
        $this->phase = new ProcedurePhase();
        $this->publicParticipationPhase = new ProcedurePhase();
    }

    /**
     * @return Collection<int,Elements>
     */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    /**
     * @param Collection<int, Elements> $elements
     */
    public function setElements($elements)
    {
        $this->elements = $elements;
    }

    public function addElement(ElementsInterface $element): void
    {
        $this->elements->add($element);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @deprecated use {@link ProcedureInterface::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set pName.
     *
     * @param string $name
     *
     * @return Procedure
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get pName.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set oName.
     *
     * @param string $orgaName
     *
     * @return Procedure
     */
    public function setOrgaName($orgaName)
    {
        $this->orgaName = $orgaName;

        return $this;
    }

    /**
     * Get oName.
     *
     * @return string
     */
    public function getOrgaName()
    {
        return $this->orgaName;
    }

    /**
     * @return string|null
     */
    public function getOrgaId()
    {
        $return = null;
        if (isset($this->orga)) {
            $this->orgaId = $this->orga->getId();
            $return = $this->orga->getId();
        }

        return $return;
    }

    public function getOrga(): ?Orga
    {
        return $this->orga;
    }

    /**
     * @param OrgaInterface $orga
     *
     * @return $this
     */
    public function setOrga($orga)
    {
        $this->orga = $orga;

        return $this;
    }

    /**
     * Set pDesc.
     *
     * @param string $desc
     *
     * @return Procedure
     */
    public function setDesc($desc)
    {
        $this->desc = $desc;

        return $this;
    }

    /**
     * Get pDesc.
     *
     * @return string
     */
    public function getDesc()
    {
        return $this->desc;
    }

    /**
     * @param string $phaseKey
     */
    public function setPhase($phaseKey): Procedure
    {
        $this->setPhaseKey($phaseKey);

        return $this;
    }

    public function setPhaseKey($phaseKey): void
    {
        $this->phase->setKey($phaseKey);
    }

    public function getPhase(): string
    {
        return $this->phase->getKey();
    }

    public function getPhaseObject(): ProcedurePhaseInterface
    {
        return $this->phase;
    }

    public function getPhaseName(): string
    {
        return $this->phase->getName();
    }

    /**
     * @param string $phaseName
     */
    public function setPhaseName($phaseName)
    {
        $this->phase->setName($phaseName);
    }

    public function getPhasePermissionset(): string
    {
        return $this->phase->getPermissionSet() ?? ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN;
    }

    public function setPhasePermissionset(string $phasePermissionset): Procedure
    {
        $this->phase->setPermissionSet($phasePermissionset);

        return $this;
    }

    /**
     * Set pStep.
     *
     * @param string $step
     *
     * @return Procedure
     */
    public function setStep($step)
    {
        $this->phase->setStep($step);

        return $this;
    }

    /**
     * Get pStep.
     *
     * @return string
     */
    public function getStep()
    {
        return $this->phase->getStep();
    }

    /**
     * Set pLogo.
     *
     * @param string $logo
     *
     * @return Procedure
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * Get pLogo.
     *
     * @return string
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * Set pExternId.
     *
     * @param string $externId
     *
     * @return Procedure
     */
    public function setExternId($externId)
    {
        $this->externId = $externId;

        return $this;
    }

    /**
     * Get pExternId.
     *
     * @return string
     */
    public function getExternId()
    {
        return $this->externId;
    }

    /**
     * Set pPlisId.
     *
     * @param string $plisId
     *
     * @return Procedure
     */
    public function setPlisId($plisId)
    {
        $this->plisId = $plisId;

        return $this;
    }

    /**
     * Get pPlisId.
     *
     * @return string
     */
    public function getPlisId()
    {
        return $this->plisId;
    }

    /**
     * Set pClosed.
     *
     * @param bool $closed
     *
     * @return Procedure
     */
    public function setClosed($closed)
    {
        $this->closed = \filter_var($closed, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get pClosed.
     *
     * @return bool
     */
    public function getClosed()
    {
        return \filter_var($this->closed, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return Procedure
     */
    public function setDeleted($deleted)
    {
        $this->deleted = \filter_var($deleted, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get Deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return \filter_var($this->deleted, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Is Deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return \filter_var($this->deleted, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Tells Elasticsearch whether Entity should be indexed.
     *
     * @return bool
     */
    public function shouldBeIndexed()
    {
        return !$this->isDeleted();
    }

    /**
     * Set pMaster.
     *
     * @param bool $master
     *
     * @return Procedure
     */
    public function setMaster($master)
    {
        $this->master = \filter_var($master, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get pMaster.
     *
     * @return bool
     */
    public function getMaster()
    {       // improve: use is instead of get
        return \filter_var($this->master, FILTER_VALIDATE_BOOLEAN);
    }

    public function setMasterTemplate(bool $masterTemplate): Procedure
    {
        $this->masterTemplate = $masterTemplate;

        return $this;
    }

    public function isMasterTemplate(): bool
    {
        return $this->masterTemplate;
    }

    /**
     * Set pExternalName.
     *
     * @param string $externalName
     *
     * @return Procedure
     */
    public function setExternalName($externalName)
    {
        $this->externalName = $externalName;

        return $this;
    }

    /**
     * Get pExternalName.
     */
    public function getExternalName(): string
    {
        return $this->externalName;
    }

    /**
     * Set pExternalDesc.
     *
     * @param string $externalDesc
     *
     * @return Procedure
     */
    public function setExternalDesc($externalDesc)
    {
        $this->externalDesc = $externalDesc;

        return $this;
    }

    /**
     * Get pExternalDesc.
     *
     * @return string
     */
    public function getExternalDesc()
    {
        return $this->externalDesc;
    }

    /**
     * Set pPublicParticipation.
     *
     * @param bool $publicParticipation
     *
     * @return Procedure
     */
    public function setPublicParticipation($publicParticipation)
    {
        $this->publicParticipation = \filter_var($publicParticipation, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get pPublicParticipation.
     *
     * @return bool
     */
    public function getPublicParticipation()
    {
        return \filter_var($this->publicParticipation, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set pPublicParticipationPhase.
     *
     * @param string $publicParticipationPhaseKey
     *
     * @return Procedure
     */
    public function setPublicParticipationPhase($publicParticipationPhaseKey)
    {
        $this->publicParticipationPhase->setKey($publicParticipationPhaseKey);

        return $this;
    }

    public function getPublicParticipationPhase(): string
    {
        return $this->publicParticipationPhase->getKey();
    }

    public function getPublicParticipationPhaseObject(): ProcedurePhaseInterface
    {
        return $this->publicParticipationPhase;
    }

    /**
     * @return string
     */
    public function getPublicParticipationPhaseName()
    {
        return $this->publicParticipationPhase->getName();
    }

    /**
     * @param string $publicParticipationPhaseName
     */
    public function setPublicParticipationPhaseName($publicParticipationPhaseName)
    {
        $this->publicParticipationPhase->setName($publicParticipationPhaseName);
    }

    public function getPublicParticipationPhasePermissionset(): string
    {
        return $this->publicParticipationPhase->getPermissionSet() ??
            ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN;
    }

    public function setPublicParticipationPhasePermissionset(string $publicParticipationPhasePermissionset): Procedure
    {
        $this->publicParticipationPhase->setPermissionSet($publicParticipationPhasePermissionset);

        return $this;
    }

    /**
     * @param string $publicParticipationStep
     */
    public function setPublicParticipationStep($publicParticipationStep): Procedure
    {
        $this->publicParticipationPhase->setStep($publicParticipationStep);

        return $this;
    }

    /**
     * Get pPublicParticipationStep.
     *
     * @return string
     */
    public function getPublicParticipationStep()
    {
        return $this->publicParticipationPhase->getStep();
    }

    /**
     * Set pPublicParticipationStart.
     *
     * @param DateTime $publicParticipationStartDate
     *
     * @return Procedure
     */
    public function setPublicParticipationStartDate($publicParticipationStartDate)
    {
        $this->publicParticipationPhase->setStartDate($publicParticipationStartDate);

        return $this;
    }

    /**
     * Get pPublicParticipationStart.
     *
     * @return DateTime
     */
    public function getPublicParticipationStartDate()
    {
        return $this->publicParticipationPhase->getStartDate();
    }

    /**
     * Get publicParticipationStartDate as Timestamp.
     *
     * @return int
     */
    public function getPublicParticipationStartDateTimestamp()
    {
        if (($this->getPublicParticipationStartDate() instanceof DateTime) && is_numeric($this->getPublicParticipationStartDate()->getTimestamp())) {
            return $this->getPublicParticipationStartDate()->getTimestamp();
        }

        return 7200;
    }

    /**
     * Set pPublicParticipationEnd.
     *
     * @param DateTime $publicParticipationEndDate
     *
     * @return Procedure
     */
    public function setPublicParticipationEndDate($publicParticipationEndDate)
    {
        $this->publicParticipationPhase->setEndDate($publicParticipationEndDate);

        return $this;
    }

    /**
     * Get pPublicParticipationEnd.
     *
     * @return DateTime
     */
    public function getPublicParticipationEndDate()
    {
        return $this->publicParticipationPhase->getEndDate();
    }

    /**
     * Get pPublicParticipationEnd as Timestamp.
     *
     * @return int
     */
    public function getPublicParticipationEndDateTimestamp()
    {
        if (($this->getPublicParticipationEndDate() instanceof DateTime) && is_numeric($this->getPublicParticipationEndDate()->getTimestamp())) {
            return $this->getPublicParticipationEndDate()->getTimestamp();
        }

        return 7200;
    }

    /**
     * Get publicParticipationPublicationEnabled.
     *
     * @return bool
     */
    public function getPublicParticipationPublicationEnabled()
    {
        return \filter_var($this->publicParticipationPublicationEnabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set publicParticipationPublicationEnabled.
     *
     * @param bool $enabled
     *
     * @return Procedure
     */
    public function setPublicParticipationPublicationEnabled($enabled)
    {
        $this->publicParticipationPublicationEnabled = \filter_var($enabled, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Set pPublicParticipationContact.
     *
     * @param string $publicParticipationContact
     *
     * @return Procedure
     */
    public function setPublicParticipationContact($publicParticipationContact)
    {
        $this->publicParticipationContact = $publicParticipationContact;

        return $this;
    }

    /**
     * Get pPublicParticipationContact.
     *
     * @return string
     */
    public function getPublicParticipationContact()
    {
        return $this->publicParticipationContact;
    }

    /**
     * Set pLocationName.
     *
     * @param string $locationName
     *
     * @return Procedure
     */
    public function setLocationName($locationName)
    {
        $this->locationName = $locationName;

        return $this;
    }

    /**
     * Get pLocationName.
     *
     * @return string
     */
    public function getLocationName()
    {
        return $this->locationName;
    }

    /**
     * Set pLocationPostCode.
     *
     * @param string $locationPostCode
     *
     * @return Procedure
     */
    public function setLocationPostCode($locationPostCode)
    {
        $this->locationPostCode = $locationPostCode;

        return $this;
    }

    /**
     * Get pLocationPostCode.
     *
     * @return string
     */
    public function getLocationPostCode()
    {
        return $this->locationPostCode;
    }

    /**
     * @return string
     */
    public function getCoordinate()
    {
        return $this->settings->getCoordinate();
    }

    /**
     * Getter used to populate Elasticsearch.
     */
    public function isParticipationGuestOnly(): bool
    {
        return $this->procedureBehaviorDefinition instanceof ProcedureBehaviorDefinition
            ? $this->procedureBehaviorDefinition->isParticipationGuestOnly()
            : false;
    }

    /**
     * @return string
     */
    public function getPlanningArea()
    {
        // get default value
        $planningArea = 'all';

        // return default Value instead of empty String
        if ('' === $this->settings->getPlanningArea()) {
            return $planningArea;
        }

        return $this->settings->getPlanningArea();
    }

    /**
     * Set pMunicipalCode.
     *
     * @param string $municipalCode
     *
     * @return Procedure
     */
    public function setMunicipalCode($municipalCode)
    {
        $this->municipalCode = $municipalCode;

        return $this;
    }

    /**
     * Get pMunicipalCode.
     *
     * @return string
     */
    public function getMunicipalCode()
    {
        return $this->municipalCode;
    }

    public function getArs(): string
    {
        return $this->ars;
    }

    public function setArs(string $ars): Procedure
    {
        $this->ars = $ars;

        return $this;
    }

    /**
     * Set pCreatedDate.
     *
     * @param DateTime $createdDate
     *
     * @return Procedure
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * Get pCreatedDate.
     *
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * Set pStartDate.
     *
     * @param DateTime $startDate
     *
     * @return Procedure
     */
    public function setStartDate($startDate)
    {
        $this->phase->setStartDate($startDate);

        return $this;
    }

    /**
     * Get pStartDate.
     *
     * @return DateTime
     */
    public function getStartDate()
    {
        return $this->phase->getStartDate();
    }

    /**
     * Get getStartDate as Timestamp.
     *
     * @return int
     */
    public function getStartDateTimestamp()
    {
        if ($this->getStartDate() instanceof DateTime) {
            return $this->getStartDate()->getTimestamp();
        }

        return 7200;
    }

    /**
     * Set pEndDate.
     *
     * @param DateTime $endDate
     *
     * @return Procedure
     */
    public function setEndDate($endDate)
    {
        $this->phase->setEndDate($endDate);

        return $this;
    }

    /**
     * Get pEndDate.
     *
     * @return DateTime
     */
    public function getEndDate()
    {
        return $this->phase->getEndDate();
    }

    /**
     * Get getEndDate as Timestamp.
     *
     * @return int
     */
    public function getEndDateTimestamp()
    {
        if ($this->getEndDate() instanceof DateTime) {
            return $this->getEndDate()->getTimestamp();
        }

        return 7200;
    }

    /**
     * Set pClosedDate.
     *
     * @param DateTime $closedDate
     *
     * @return Procedure
     */
    public function setClosedDate($closedDate)
    {
        $this->closedDate = $closedDate;

        return $this;
    }

    /**
     * Get pClosedDate.
     *
     * @return DateTime
     */
    public function getClosedDate()
    {
        return $this->closedDate;
    }

    /**
     * Set pDeletedDate.
     *
     * @param DateTime $deletedDate
     *
     * @return Procedure
     */
    public function setDeletedDate($deletedDate)
    {
        $this->deletedDate = $deletedDate;

        return $this;
    }

    /**
     * Get pDeletedDate.
     *
     * @return DateTime
     */
    public function getDeletedDate()
    {
        return $this->deletedDate;
    }

    /**
     * Set organisations like plannig agencies
     * Does not contains the owning organisation.
     *
     * @param array $organisation
     *
     * @return Procedure
     */
    public function setOrganisation($organisation)
    {
        $this->organisation = new ArrayCollection($organisation);

        return $this;
    }

    /**
     * Add Organisation to this Procedure.
     *
     * @param OrgaInterface $organisation - Organisation to add
     *
     * @return Procedure - updated Procedure
     */
    public function addOrganisation(OrgaInterface $organisation)
    {
        // hasOrganisation()
        if (false === $this->hasOrganisation($organisation->getId())) {
            $this->organisation->add($organisation);
        }

        return $this;
    }

    /**
     * Detach a Organisation from this Procedure.
     *
     * @return Procedure
     */
    public function removeOrganisation(OrgaInterface $organisation)
    {
        if ($this->hasOrganisation($organisation->getId())) {
            $this->organisation->removeElement($organisation);
        }

        return $this;
    }

    /**
     * Get o.
     *
     * @return ArrayCollection
     */
    public function getOrganisation()
    {
        return $this->organisation;
    }

    /**
     * Warning: This method may lead to performance issues as every organisation is hydrated by
     * doctrine.
     * When possible use {@link ProcedureRepository::getInvitedOrgaIds()} instead which returns the same
     * values.
     *
     * @return string[]
     */
    public function getOrganisationIds()
    {
        $organisations = $this->getOrganisation();
        $orgaIds = [];
        foreach ($organisations as $orga) {
            $orgaIds[] = $orga->getId();
        }

        return $orgaIds;
    }

    /**
     * Is procedure added to Organisation.
     *
     * @param string $orgaId
     *
     * @return bool
     */
    public function hasOrganisation($orgaId)
    {
        if ($this->organisation instanceof Collection) {
            $existingOrga = $this->organisation->filter(fn ($entry) => $entry->getId() == $orgaId);
            if (0 < $existingOrga->count()) {
                return true;
            }
        }

        return false;
    }

    public function getSettings(): ProcedureSettings
    {
        return $this->settings;
    }

    public function setSettings(ProcedureSettingsInterface $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Get all tags that are available in this procedure.
     *
     * @return ArrayCollection<int,Tag>
     */
    public function getTags(): ArrayCollection
    {
        $collection = new ArrayCollection();
        /** @var TagTopic $topic */
        foreach ($this->topics as $topic) {
            /** @var Tag $tag */
            foreach ($topic->getTags() as $tag) {
                $collection->add($tag);
            }
        }

        return $collection;
    }

    /**
     * Get all topics that are available in this procedure.
     *
     * @return ArrayCollection
     */
    public function getTopics()
    {
        return $this->topics;
    }

    public function detachAllTopics(): void
    {
        $this->topics->clear();
    }

    /**
     * @return Collection<int, Orga>
     */
    public function getPlanningOffices(): Collection
    {
        return $this->planningOffices;
    }

    /**
     * @param OrgaInterface[] $planningOffices
     */
    public function setPlanningOffices($planningOffices): self
    {
        $this->planningOffices = new ArrayCollection($planningOffices);
        $this->planningOffices->forAll(fn ($key, OrgaInterface $planningOffice): bool => $planningOffice->addAdministratableProcedure($this));

        return $this;
    }

    public function addPlanningOffice(OrgaInterface $planningOffice): self
    {
        if (!$this->planningOffices->contains($planningOffice)) {
            $this->planningOffices->add($planningOffice);
        }

        return $this;
    }

    /**
     * @return Collection<int, Orga>
     */
    public function getDataInputOrganisations()
    {
        return $this->dataInputOrganisations;
    }

    /**
     * Get dataInputOrgaIds as array.
     *
     * @return string[]
     */
    public function getDataInputOrgaIds()
    {
        $dataInputOrgaIds = [];
        foreach ($this->dataInputOrganisations as $orga) {
            if ($orga instanceof Orga) {
                $dataInputOrgaIds[] = $orga->getId();
            }
        }

        return $dataInputOrgaIds;
    }

    /**
     * @param OrgaInterface[] $dataInputOrganisations
     *
     * @return Procedure
     */
    public function setDataInputOrganisations($dataInputOrganisations)
    {
        $this->dataInputOrganisations = new ArrayCollection($dataInputOrganisations);

        return $this;
    }

    public function getPictogram(): ?string
    {
        return $this->settings->getPictogram();
    }

    /**
     * @return Collection<int, NotificationReceiver>
     */
    public function getNotificationReceivers(): Collection
    {
        return $this->notificationReceivers;
    }

    /**
     * @param NotificationReceiverInterface[] $notificationReceivers
     */
    public function setNotificationReceivers(array $notificationReceivers): void
    {
        $this->notificationReceivers = $notificationReceivers;
    }

    /**
     * T8427
     * Allow additional restriction of access to this Procedure.
     * CustomizedAuthorizedUsers.
     *
     * @return Collection<int, User>
     */
    public function getAuthorizedUsers(): Collection
    {
        return $this->authorizedUsers;
    }

    /**
     * @param ArrayCollection|array $authorizedUsers
     */
    public function setAuthorizedUsers($authorizedUsers): self
    {
        if (is_array($authorizedUsers)) {
            $authorizedUsers = new ArrayCollection($authorizedUsers);
        }

        $this->authorizedUsers = $authorizedUsers;
        $this->authorizedUsers->forAll(fn ($key, User $user): bool => $user->addAuthorizedProcedure($this));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAuthorizedUserNames()
    {
        $userNames = [];

        /** @var User $user */
        foreach ($this->getAuthorizedUsers() as $user) {
            $userNames[] = $user->getFullname();
        }

        return $userNames;
    }

    /**
     * @return string[]
     */
    public function getAuthorizedUserIds(): array
    {
        $userIds = [];

        /** @var User $user */
        foreach ($this->getAuthorizedUsers() as $user) {
            $userIds[] = $user->getId();
        }

        return $userIds;
    }

    /**
     * Warning: This method may lead to performance issues as every organisation is hydrated by
     * doctrine.
     * When possible use {@link ProcedureRepository::getPlanningOfficeIds()} instead which returns the same
     * values.
     *
     * Returns Ids of procedures' planning offices.
     *
     * @return string[]
     */
    public function getPlanningOfficesIds()
    {
        $planningOfficeIds = [];

        /** @var Orga[] $planningOffices */
        $planningOffices = $this->getPlanningOffices();
        foreach ($planningOffices as $organisation) {
            $planningOfficeIds[] = $organisation->getId();
        }

        return $planningOfficeIds;
    }

    /**
     * @return string
     */
    public function getLegalNotice()
    {
        return $this->getSettings()->getLegalNotice();
    }

    /**
     * @return string
     */
    public function getAgencyMainEmailAddress()
    {
        return $this->agencyMainEmailAddress;
    }

    /**
     * @param string $agencyMainEmailAddress
     *
     * @return $this
     */
    public function setAgencyMainEmailAddress($agencyMainEmailAddress)
    {
        $this->agencyMainEmailAddress = $agencyMainEmailAddress;

        return $this;
    }

    /**
     * @return Collection<int, EmailAddress>
     */
    public function getAgencyExtraEmailAddresses()
    {
        return $this->agencyExtraEmailAddresses;
    }

    /**
     * @param Collection<int, EmailAddressInterface> $agencyExtraEmailAddresses
     *
     * @return $this
     */
    public function setAgencyExtraEmailAddresses(Collection $agencyExtraEmailAddresses): Procedure
    {
        $this->agencyExtraEmailAddresses = $agencyExtraEmailAddresses;

        return $this;
    }

    /**
     * @param Collection<int, EmailAddressInterface> $agencyExtraEmailAddresses
     *
     * @return $this
     */
    public function addAgencyExtraEmailAddresses(Collection $agencyExtraEmailAddresses): Procedure
    {
        foreach ($agencyExtraEmailAddresses as $agencyExtraEmailAddress) {
            if (!$this->agencyExtraEmailAddresses->contains($agencyExtraEmailAddress)) {
                $this->agencyExtraEmailAddresses[] = $agencyExtraEmailAddress;
            }
        }

        return $this;
    }

    /**
     * Returns a blankspace separated list with the Orga Slugs (current slug plus the previous ones that the Orga might had).
     *
     * @return string
     */
    public function getOrgaSlugs()
    {
        $slugsArray = $this->getOrga()->getSlugs()->map(fn (Slug $slug) => $slug->getName())->toArray();

        return implode(' ', $slugsArray);
    }

    /**
     * Set pShortUrl.
     *
     * @param string $shortUrl
     *
     * @return Procedure
     */
    public function setShortUrl($shortUrl)
    {
        $this->shortUrl = $shortUrl;

        return $this;
    }

    /**
     * Get pShortUrl.
     *
     * @return string
     */
    public function getShortUrl()
    {
        return $this->currentSlug->getName();
    }

    public function getSubdomain(): string
    {
        // procedures should have a customer nowadays
        if ($this->getCustomer() instanceof Customer) {
            return $this->getCustomer()->getSubdomain();
        }

        return $this->getSubdomainByLegacyLogic();
    }

    /**
     * This should not be used any more as it is not reliable and procedures
     * should have a customer.
     *
     * @deprecated use {@link ProcedureInterface::getSubdomain()} instead
     */
    private function getSubdomainByLegacyLogic(): string
    {
        $orga = $this->getOrga();
        if (!$orga instanceof Orga) {
            return '';
        }
        $customer = $orga->getMainCustomer();
        if (null === $customer) {
            return '';
        }

        return $customer->getSubdomain();
    }

    public function isCustomerMasterBlueprint(): bool
    {
        // the customer holds the reference to the default-customer-blueprint
        return $this->getId() === $this->getCustomer()?->getDefaultProcedureBlueprint()?->getId();
    }

    /**
     * @return Customer|null
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @return string|null
     */
    public function getCustomerId()
    {
        return null === $this->customer ?: $this->customer->getId();
    }

    /**
     * @param CustomerInterface|null $customer
     */
    public function setCustomer($customer): Procedure
    {
        $this->customer = $customer;

        return $this;
    }

    public function getProcedureCategories(): Collection
    {
        return $this->procedureCategories;
    }

    public function setProcedureCategories(array $procedureCategories): void
    {
        $this->procedureCategories = new ArrayCollection($procedureCategories);
    }

    /**
     * Add ProcedureCategory to this Procedure.
     *
     * @return Procedure - updated Procedure
     */
    public function addProcedureCategory(ProcedureCategoryInterface $procedureCategory): Procedure
    {
        if (false === $this->hasProcedureCategory($procedureCategory)) {
            $this->procedureCategories->add($procedureCategory);
        }

        return $this;
    }

    /**
     * Is ProcedureCategory attached to Procedure.
     */
    public function hasProcedureCategory(ProcedureCategoryInterface $procedureCategory): bool
    {
        if ($this->procedureCategories instanceof Collection) {
            $existingProcedureCategory = $this->procedureCategories->filter(
                static fn (ProcedureCategory $entry) => $entry->getId() === $procedureCategory->getId()
            );
            if (0 < $existingProcedureCategory->count()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detach ProcedureCategory from this Procedure.
     */
    public function removeProcedureCategory(ProcedureCategoryInterface $procedureCategory): self
    {
        if ($this->hasProcedureCategory($procedureCategory)) {
            $this->procedureCategories->removeElement($procedureCategory);
        }

        return $this;
    }

    /**
     * Detach ProcedureCategories from this Procedure.
     */
    public function removeProcedureCategories(array $procedureCategories): self
    {
        foreach ($procedureCategories as $procedureCategory) {
            $this->removeProcedureCategory($procedureCategory);
        }

        return $this;
    }

    /**
     * Used for Elasticsearch indexing.
     *
     * @return array
     */
    public function getProcedureCategoryNames()
    {
        $procedureCategoryNames = [];
        foreach ($this->procedureCategories as $procedureCategory) {
            $procedureCategoryNames[] = $procedureCategory->getName();
        }

        return $procedureCategoryNames;
    }

    /**
     * @return ArrayCollection
     */
    public function getStatements()
    {
        return $this->statements;
    }

    public function setStatements(ArrayCollection $statements): void
    {
        $this->statements = $statements;
    }

    public function getSurveys(): Collection
    {
        return $this->surveys;
    }

    /**
     * Returns first Survey in the list.
     *
     * @param string $surveyId
     */
    public function getSurvey($surveyId): ?Survey
    {
        /** @var Survey $survey */
        foreach ($this->surveys as $survey) {
            if ($survey->getId() == $surveyId) {
                return $survey;
            }
        }

        return null;
    }

    public function addSurvey(SurveyInterface $survey): void
    {
        $this->surveys[] = $survey;
    }

    public function getFirstSurvey(): ?Survey
    {
        if (count($this->surveys) > 0) {
            return $this->surveys[0];
        }

        return null;
    }

    public function getProcedureBehaviorDefinition(): ?ProcedureBehaviorDefinition
    {
        return $this->procedureBehaviorDefinition;
    }

    public function getProcedureUiDefinition(): ?ProcedureUiDefinition
    {
        return $this->procedureUiDefinition;
    }

    public function getStatementFormDefinition(): ?StatementFormDefinition
    {
        return $this->statementFormDefinition;
    }

    public function clearProcedureTypeDefinitions(): void
    {
        $this->statementFormDefinition = null;
        $this->procedureUiDefinition = null;
        $this->procedureBehaviorDefinition = null;
    }

    public function getProcedureType(): ?ProcedureType
    {
        return $this->procedureType;
    }

    public function setProcedureType(ProcedureTypeInterface $procedureType): void
    {
        $this->procedureType = $procedureType;
    }

    public function setProcedureBehaviorDefinition(ProcedureBehaviorDefinitionInterface $procedureBehaviorDefinition): void
    {
        $this->procedureBehaviorDefinition = $procedureBehaviorDefinition;
    }

    public function setProcedureUiDefinition(ProcedureUiDefinitionInterface $procedureUiDefinition): void
    {
        $this->procedureUiDefinition = $procedureUiDefinition;
    }

    public function setStatementFormDefinition(StatementFormDefinitionInterface $statementFormDefinition): void
    {
        $this->statementFormDefinition = $statementFormDefinition;
    }

    /**
     * In first implementation each procedure will only have one related ExportFieldsConfiguration.
     * This Method is used to get this one.
     *
     * @throws MissingDataException
     */
    public function getDefaultExportFieldsConfiguration(): ExportFieldsConfiguration
    {
        $defaultExportFieldConfiguration = $this->exportFieldsConfigurations->first();
        if (false === $defaultExportFieldConfiguration) {
            throw new MissingDataException('Related ExportFieldsConfiguration is missing for this procedure.');
        }

        return $defaultExportFieldConfiguration;
    }

    /**
     * @return Collection<int, ExportFieldsConfiguration>
     */
    public function getExportFieldsConfigurations(): Collection
    {
        return $this->exportFieldsConfigurations;
    }

    public function clearExportFieldsConfiguration(): void
    {
        $this->exportFieldsConfigurations = new ArrayCollection();
    }

    public function addExportFieldsConfiguration(ExportFieldsConfigurationInterface $exportFieldsConfiguration): self
    {
        $this->exportFieldsConfigurations->add($exportFieldsConfiguration);

        return $this;
    }

    public function isInPublicParticipationPhase(): bool
    {
        return ProcedureInterface::PROCEDURE_PARTICIPATION_PHASE === $this->getPublicParticipationPhase();
    }

    public function addTagTopic(TagTopicInterface $tagTopic): void
    {
        $this->topics->add($tagTopic);
    }

    /**
     * @return Collection<int, File>
     */
    public function getFiles()
    {
        return $this->files;
    }

    public function setFiles(?ArrayCollection $files): void
    {
        $this->files = $files;
    }

    public function getXtaPlanId(): string
    {
        return $this->xtaPlanId;
    }

    public function setXtaPlanId(string $xtaPlanId): self
    {
        $this->xtaPlanId = $xtaPlanId;

        return $this;
    }

    /**
     * @return Collection<int, Place>
     */
    public function getSegmentPlaces(): Collection
    {
        return $this->segmentPlaces;
    }

    public function addSegmentPlace(PlaceInterface $place): void
    {
        if (!$this->segmentPlaces->contains($place)) {
            $this->segmentPlaces->add($place);
        }
    }

    /**
     * This is only needed to clone a procedure incl. phase
     * in order to create an "image" of the procedure before its update.
     */
    public function setPhaseObject(ProcedurePhaseInterface $phase): void
    {
        $this->phase = $phase;
    }

    /**
     * This is only needed to clone a procedure incl. phase
     * in order to create an "image" of the procedure before its update.
     */
    public function setPublicParticipationPhaseObject(ProcedurePhaseInterface $publicParticipationPhase): void
    {
        $this->publicParticipationPhase = $publicParticipationPhase;
    }

    public function setSegmentCustomFieldsTemplate(CustomFieldInterface $segmentCustomFieldsTemplate): void
    {
        $this->segmentCustomFieldsTemplate = $segmentCustomFieldsTemplate;
    }

    public function getSegmentCustomFieldsTemplate(): ?CustomFieldInterface
    {
        return $this->segmentCustomFieldsTemplate;
    }


    public function getCustomFieldConfiguration(): ?CustomFieldConfiguration
    {
        return $this->customFieldConfiguration;
    }
}
