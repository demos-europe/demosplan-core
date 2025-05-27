<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\BrandingInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerCountyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserRoleInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\VideoInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="customer")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CustomerRepository")
 */
class Customer extends CoreEntity implements UuidEntityInterface, CustomerInterface, Stringable
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_c_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;
    /**
     * @var Collection<int, CustomerCountyInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\CustomerCounty", mappedBy="customer", cascade={"persist"})
     */
    private $customerCounties;
    /**
     * @var string
     *
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    private $imprint = '';
    /**
     * $orgas not mapped to a Table because they are now retrieved from {@link Customer::$orgaStatuses}.
     *
     * @var ArrayCollection
     */
    private $orgas;
    /**
     * @var Collection<int, UserRoleInCustomerInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer", mappedBy="customer")
     */
    protected $userRoles;
    /**
     * @var Collection<int, OrgaStatusInCustomerInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer", mappedBy="customer")
     */
    protected $orgaStatuses;
    /**
     * Data privacy protection setting of the customer which is displayed as legal requirement on the website.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/impressum/ Wiki: Impressum / Datenschutz / Nutz.b.
     *
     * @ORM\Column(name="data_protection", type="text", length=65535, nullable=false)
     *
     * @var string
     */
    #[Assert\Length(max: 65000, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $dataProtection = '';
    /**
     * Terms of use setting of the customer which is displayed as legal requirement on the website.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/impressum/ Wiki: Impressum / Datenschutz / Nutz.b.
     *
     * @ORM\Column(type="text", length=65535, nullable=false)
     *
     * @var string
     */
    #[Assert\Length(max: 65000, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $termsOfUse = '';
    /**
     * Information page about xplanning. Should possibly be moved someday to some kind of cms like system.
     *
     * @ORM\Column(type="text", length=65535, nullable=false)
     *
     * @var string
     */
    #[Assert\Length(max: 65000, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $xplanning = '';
    /**
     * T15644:.
     *
     * @var ProcedureInterface
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", cascade={"remove"})
     *
     * @ORM\JoinColumn(name="_procedure", referencedColumnName="_p_id", nullable=true)
     */
    protected $defaultProcedureBlueprint;
    /**
     * T16986
     * Will be used to store licence information about used map by customer.
     * e.g. "© basemap.de BKG".
     *
     * @var string
     *
     * @ORM\Column(type="text", length=4096, nullable=false)
     */
    #[Assert\Length(min: 0, max: 4096, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $mapAttribution = '';
    /**
     * T16986
     * A short Url with an ID as parameter.
     * This defines the baselayer chosen by this customer.
     * e.g. "https://sgx.geodatenzentrum.de/wms_basemapde".
     *
     * @var string
     *
     *@ORM\Column(type="string", length=4096, nullable=false, options={"default":""})
     */
    #[Assert\Length(min: 0, max: 4096, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $baseLayerUrl = '';
    /**
     * T16986
     * Layer of the baserlayers in public area.
     * Additional layer on the baseLayer. E.g. used for coloring.
     * e.g. "de_basemapde_web_raster_grau".
     *
     * @var string
     *
     *@ORM\Column(type="string", length=4096, nullable=false, options={"default":""})
     */
    #[Assert\Length(min: 0, max: 4096, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $baseLayerLayers = '';
    /**
     * @var BrandingInterface|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Branding", cascade={"persist", "remove"})
     */
    #[Assert\Valid]
    protected $branding;
    /**
     * @var string
     *
     * @ORM\Column(name="accessibility_explanation", type="text",  nullable=false, options={"fixed":true})
     */
    #[Assert\Length(max: 65000, groups: [CustomerInterface::GROUP_UPDATE])]
    protected $accessibilityExplanation = '';
    /**
     * Optional videos explaining the content and basic navigation of the website in sign language.
     *
     * @var Collection<int, VideoInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Video")
     *
     * @ORM\JoinTable(name="sign_language_overview_video",
     *      joinColumns={@ORM\JoinColumn(name="customer_id", referencedColumnName="_c_id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="video_id", referencedColumnName="id", unique=true)}
     * )
     */
    private $signLanguageOverviewVideos;
    /**
     * Description text for the page in which {@link CustomerInterface::$signLanguageOverviewVideos} are shown.
     *
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    private $signLanguageOverviewDescription = '';
    /**
     * A text that will be shown on a separate page, explaining content and navigation of the
     * website in simple language.
     *
     * @var string
     *
     * @ORM\Column(name="simple_language_overview_description", type="text", nullable=false)
     */
    #[Assert\Length(max: 65536)]
    protected $overviewDescriptionInSimpleLanguage = '';

    /**
     * @var Collection<int, SupportContact>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\SupportContact", mappedBy="customer")
     */
    #[Assert\Valid]
    protected Collection $contacts;

    /**
     * @var Collection<int, InstitutionTagCategory>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory", mappedBy="customer", cascade={"remove"})
     */
    #[Assert\Valid]
    protected Collection $customerCategories;

    public function __construct(/**
     * @ORM\Column(name="_c_name", type="string", length=50, nullable=false)
     */
        private string $name, /**
     * @ORM\Column(name="_c_subdomain", type="string", length=50, nullable=false)
     */
        private string $subdomain, string $mapAttribution = '')
    {
        $this->mapAttribution = $mapAttribution;
        $this->userRoles = new ArrayCollection();
        $this->orgaStatuses = new ArrayCollection();
        $this->signLanguageOverviewVideos = new ArrayCollection();
        $this->customerCounties = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->customerCategories = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return Collection<int, CustomerCountyInterface>
     */
    public function getCustomerCounties(): Collection
    {
        return $this->customerCounties;
    }

    /**
     * @param Collection<int, CustomerCountyInterface> $customerCounties
     */
    public function setCustomerCounties(Collection $customerCounties): void
    {
        $this->customerCounties = $customerCounties;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain)
    {
        $this->subdomain = $subdomain;
    }

    /**
     * @return OrgaStatusInCustomerInterface[]
     */
    public function getOrgaStatuses()
    {
        return $this->orgaStatuses;
    }

    /**
     * @param Collection<int, OrgaStatusInCustomerInterface> $orgaStatuses
     */
    public function setOrgaStatuses(Collection $orgaStatuses)
    {
        $this->orgaStatuses = $orgaStatuses;
    }

    /**
     * @param array<int,string> $statuses if null, all orga statuses will be returned
     *                                    will be applied as or condition
     *
     * @return Collection<int, Orga>
     */
    public function getOrgas(array $statuses = []): Collection
    {
        $orgas = new ArrayCollection();

        /** @var OrgaStatusInCustomerInterface $customerOrgaTypes */
        foreach ($this->getOrgaStatuses() as $customerOrgaTypes) {
            $orga = $customerOrgaTypes->getOrga();
            if (!$orgas->contains($orga)) {
                // filter by status
                if ([] === $statuses || in_array($customerOrgaTypes->getStatus(), $statuses, true)) {
                    $orgas->add($orga);
                }
            }
        }

        return $orgas;
    }

    /**
     * @param Collection<int, OrgaInterface> $orgas
     */
    public function setOrgas(Collection $orgas)
    {
        $this->orgas->clear();
        foreach ($orgas as $orga) {
            $this->addOrga($orga);
        }
    }

    /**
     * @return Collection<int, UserRoleInCustomerInterface>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addOrga(OrgaInterface $orga): bool
    {
        if (!$this->orgas->contains($orga)) {
            $this->orgas[] = $orga;

            return true;
        }

        return false;
    }

    public function removeOrga(OrgaInterface $orga)
    {
        $this->orgas->removeElement($orga);
    }

    public function __toString(): string
    {
        return $this->id ?? '';
    }

    public function getImprint(): string
    {
        return $this->imprint;
    }

    public function setImprint(string $imprint)
    {
        $this->imprint = $imprint;
    }

    public function getDataProtection(): string
    {
        return $this->dataProtection;
    }

    public function setDataProtection(string $dataProtection)
    {
        $this->dataProtection = $dataProtection;
    }

    /**
     * Set terms of use.
     */
    public function getTermsOfUse(): string
    {
        return $this->termsOfUse;
    }

    /**
     * Get terms of use.
     */
    public function setTermsOfUse(string $termsOfUse): void
    {
        $this->termsOfUse = $termsOfUse;
    }

    public function getXplanning(): string
    {
        return $this->xplanning;
    }

    public function setXplanning(string $xplanning)
    {
        $this->xplanning = $xplanning;
    }

    public function getDefaultProcedureBlueprint(): ?ProcedureInterface
    {
        return $this->defaultProcedureBlueprint?->isDeleted() ? null : $this->defaultProcedureBlueprint;
    }

    /**
     * @param ProcedureInterface|null $defaultProcedureBlueprint
     */
    public function setDefaultProcedureBlueprint($defaultProcedureBlueprint): void
    {
        $this->defaultProcedureBlueprint = $defaultProcedureBlueprint;
    }

    public function setMapAttribution(string $mapAttribution): void
    {
        $this->mapAttribution = $mapAttribution;
    }

    public function getMapAttribution(): string
    {
        return $this->mapAttribution;
    }

    public function setBaseLayerUrl(string $baseLayerUrl): void
    {
        $this->baseLayerUrl = $baseLayerUrl;
    }

    public function getBaseLayerUrl(): string
    {
        return $this->baseLayerUrl;
    }

    public function getBaseLayerLayers(): string
    {
        return $this->baseLayerLayers;
    }

    public function setBaseLayerLayers(string $baseLayerLayers): void
    {
        $this->baseLayerLayers = $baseLayerLayers;
    }

    public function getBranding(): ?BrandingInterface
    {
        return $this->branding;
    }

    public function setBranding(?BrandingInterface $branding): void
    {
        $this->branding = $branding;
    }

    public function getAccessibilityExplanation(): string
    {
        return $this->accessibilityExplanation;
    }

    public function setAccessibilityExplanation(string $accessibilityExplanation): void
    {
        $this->accessibilityExplanation = $accessibilityExplanation;
    }

    /**
     * @return Collection<int, VideoInterface>
     */
    public function getSignLanguageOverviewVideos(): Collection
    {
        return $this->signLanguageOverviewVideos;
    }

    public function addSignLanguageOverviewVideo(VideoInterface $signLanguageOverviewVideo): self
    {
        if (!$this->signLanguageOverviewVideos->contains($signLanguageOverviewVideo)) {
            $this->signLanguageOverviewVideos[] = $signLanguageOverviewVideo;
        }

        return $this;
    }

    public function removeSignLanguageOverviewVideo(VideoInterface $signLanguageOverviewVideo): self
    {
        $this->signLanguageOverviewVideos->removeElement($signLanguageOverviewVideo);

        return $this;
    }

    public function setSignLanguageOverviewDescription(string $signLanguageOverviewDescription): self
    {
        $this->signLanguageOverviewDescription = $signLanguageOverviewDescription;

        return $this;
    }

    public function getSignLanguageOverviewDescription(): string
    {
        return $this->signLanguageOverviewDescription;
    }

    public function getOverviewDescriptionInSimpleLanguage(): string
    {
        return $this->overviewDescriptionInSimpleLanguage;
    }

    public function setOverviewDescriptionInSimpleLanguage(string $overviewDescriptionInSimpleLanguage): self
    {
        $this->overviewDescriptionInSimpleLanguage = $overviewDescriptionInSimpleLanguage;

        return $this;
    }

    /**
     * @return Collection<int, SupportContact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    /**
     * @param Collection<int, SupportContact> $contacts
     */
    public function setContacts(Collection $contacts): void
    {
        $this->contacts = $contacts;
    }
}
