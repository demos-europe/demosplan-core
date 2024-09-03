<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureSettingsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_procedure_settings", indexes={@ORM\Index(name="_procedure_settings_ibfk_1", columns={"_p_id"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureSettingsRepository")
 */
class ProcedureSettings extends CoreEntity implements UuidEntityInterface, ProcedureSettingsInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_ps_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Die eigentliche Entity liegt unter $this->procedure.
     *
     * @var string
     */
    protected $pId;

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_map_extent", type="string", length=2048, nullable=false)
     */
    protected $mapExtent = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_start_scale", type="string", length=2048, nullable=false)
     */
    protected $startScale = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_available_scale", type="string", length=2048, nullable=false)
     */
    protected $availableScale = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_bounding_box", type="string", length=2048, nullable=false)
     */
    protected $boundingBox = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_information_url", type="string", length=2048, nullable=false)
     */
    protected $informationUrl = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_default_layer", type="string", length=2048, nullable=false)
     */
    protected $defaultLayer = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_territory", type="text", length=65535, nullable=false)
     */
    protected $territory = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_coordinate", type="string", length=2048, nullable=false)
     */
    protected $coordinate = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_ps_plan_enable", type="boolean", nullable=false, options={"default":false})
     */
    protected $planEnable = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_plan_text", type="text", length=65535, nullable=false)
     */
    protected $planText = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_plan_pdf", type="string", length=256, nullable=false)
     */
    protected $planPDF = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_plan_para1_pdf", type="string", length=256, nullable=false)
     */
    protected $planPara1PDF = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_plan_para2_pdf", type="string", length=256, nullable=false)
     */
    protected $planPara2PDF = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_plan_draw_text", type="text", length=65535, nullable=false)
     */
    protected $planDrawText = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_plan_draw_pdf", type="string", length=256, nullable=false)
     */
    protected $planDrawPDF = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_email_title", type="string", length=2048, nullable=false)
     */
    protected $emailTitle = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_email_text", type="text", length=65535, nullable=false)
     */
    protected $emailText = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_email_cc", type="text", length=25000, nullable=false)
     */
    protected $emailCc = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ps_links", type="text", nullable=false)
     */
    protected $links = '';

    /**
     * @var ProcedureInterface
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="settings")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_ps_pictogram", type="string", length=256, nullable=true)
     */
    protected $pictogram;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ps_send_mails_to_counties", type="boolean", nullable=false, options={"default":false})
     */
    protected $sendMailsToCounties = false;

    /**
     * Contains an identifier for a specific planningArea getting from WFS.
     *
     * @var string|null
     *
     * @ORM\Column(type="string", options={"default":"all"})
     */
    protected $planningArea = 'all';

    /**
     * Comma separated numbers as string.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $scales = '';

    /**
     * T9581
     * Legal notice to contains clause or other legal relevant information or references of this Procedure.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=false, options={"default":""})
     */
    protected $legalNotice = '';

    /**
     * T10133.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=false, options={"default":""})
     */
    protected $copyright = '';

    /**
     * Stores the text to be shown in a modal in the map in the public detail view.
     *
     * The text must be at least a couple of characters long to motivate the user to write
     * something meaningful. The length is defined in
     * {@link ProcedureSettingsInterface::MAP_HINT_MIN_LENGTH}.
     *
     * The maximum number of characters allowed is defined in
     * {@link ProcedureSettingsInterface::MAP_HINT_MAX_LENGTH}. The number should be kept relatively small
     * to limit the writer to meaningful content, as the text is intended to be read by screen
     * readers.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=2000, nullable=false, options={"default":""})
     */
    protected $mapHint = '';

    /**
     * By adding {@link ProcedureInterface}s to this relationship and activating the corresponding
     * `feature_segment_access_expansion` permission the {@link SegmentInterface}s in these procedures will
     * be returned too *if* the user owns the {@link ProcedureInterface} or has at least been invited.
     *
     * @var Collection<int,ProcedureInterface>
     *
     * @ORM\ManyToMany(targetEntity=Procedure::class)
     *
     * @ORM\JoinTable(
     *     name="procedure_settings_allowed_segment_procedures",
     *     joinColumns={@ORM\JoinColumn(referencedColumnName="_ps_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(referencedColumnName="_p_id")}
     * )
     */
    private $allowedSegmentAccessProcedures;

    public function __construct()
    {
        $this->allowedSegmentAccessProcedures = new ArrayCollection();
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
     * @return string|null
     */
    public function getPId()
    {
        $return = null;
        if (!is_null($this->procedure)) {
            $return = $this->procedure->getId();
        }

        return $return;
    }

    /**
     * Set psMapExtent.
     *
     * @param string $mapExtent
     *
     * @return ProcedureSettingsInterface
     */
    public function setMapExtent($mapExtent)
    {
        $this->mapExtent = $mapExtent;

        return $this;
    }

    /**
     * Get psMapExtent.
     *
     * @return string
     */
    public function getMapExtent()
    {
        return $this->mapExtent;
    }

    /**
     * Set psStartScale.
     *
     * @param string $startScale
     *
     * @return ProcedureSettingsInterface
     */
    public function setStartScale($startScale)
    {
        $this->startScale = $startScale;

        return $this;
    }

    /**
     * Get psStartScale.
     *
     * @return string
     */
    public function getStartScale()
    {
        return $this->startScale;
    }

    /**
     * Set psAvailableScale.
     *
     * @param string $availableScale
     *
     * @return ProcedureSettingsInterface
     */
    public function setAvailableScale($availableScale)
    {
        $this->availableScale = $availableScale;

        return $this;
    }

    /**
     * Get psAvailableScale.
     *
     * @return string
     */
    public function getAvailableScale()
    {
        return $this->availableScale;
    }

    /**
     * Set psBoundingBox.
     *
     * @param string $boundingBox
     *
     * @return ProcedureSettingsInterface
     */
    public function setBoundingBox($boundingBox)
    {
        $this->boundingBox = $boundingBox;

        return $this;
    }

    /**
     * Get psBoundingBox.
     *
     * @return string
     */
    public function getBoundingBox()
    {
        return $this->boundingBox;
    }

    /**
     * Set psInformationUrl.
     *
     * @param string $informationUrl
     *
     * @return ProcedureSettingsInterface
     */
    public function setInformationUrl($informationUrl)
    {
        $this->informationUrl = $informationUrl;

        return $this;
    }

    /**
     * Get psInformationUrl.
     *
     * @return string
     */
    public function getInformationUrl()
    {
        return $this->informationUrl;
    }

    /**
     * Set psDefaultLayer.
     *
     * @param string $defaultLayer
     *
     * @return ProcedureSettingsInterface
     */
    public function setDefaultLayer($defaultLayer)
    {
        $this->defaultLayer = $defaultLayer;

        return $this;
    }

    /**
     * Get psDefaultLayer.
     *
     * @return string
     */
    public function getDefaultLayer()
    {
        return $this->defaultLayer;
    }

    /**
     * Set psTerritory.
     *
     * @param string $territory
     *
     * @return ProcedureSettingsInterface
     */
    public function setTerritory($territory)
    {
        $this->territory = $territory;

        return $this;
    }

    /**
     * Get psTerritory.
     *
     * @return string
     */
    public function getTerritory()
    {
        return $this->territory;
    }

    /**
     * Set psCoordinate.
     *
     * @param string $coordinate
     *
     * @return ProcedureSettingsInterface
     */
    public function setCoordinate($coordinate)
    {
        $this->coordinate = $coordinate;

        return $this;
    }

    /**
     * Get psCoordinate.
     *
     * @return string
     */
    public function getCoordinate()
    {
        return $this->coordinate;
    }

    /**
     * Set psPlanEnable.
     *
     * @param bool $planEnable
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanEnable($planEnable)
    {
        $this->planEnable = (int) $planEnable;

        return $this;
    }

    /**
     * Get psPlanEnable.
     *
     * @return bool
     */
    public function getPlanEnable()
    {
        return (bool) $this->planEnable;
    }

    /**
     * Set psPlanText.
     *
     * @param string $planText
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanText($planText)
    {
        $this->planText = $planText;

        return $this;
    }

    /**
     * Get psPlanText.
     *
     * @return string
     */
    public function getPlanText()
    {
        return $this->planText;
    }

    /**
     * Set psPlanPdf.
     *
     * @param string $planPDF
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanPDF($planPDF)
    {
        $this->planPDF = $planPDF;

        return $this;
    }

    /**
     * Get psPlanPdf.
     *
     * @return string
     */
    public function getPlanPDF()
    {
        return $this->planPDF;
    }

    /**
     * Set psPlanPara1Pdf.
     *
     * @param string $planPara1PDF
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanPara1PDF($planPara1PDF)
    {
        $this->planPara1PDF = $planPara1PDF;

        return $this;
    }

    /**
     * Get psPlanPara1Pdf.
     *
     * @return string
     */
    public function getPlanPara1PDF()
    {
        return $this->planPara1PDF;
    }

    /**
     * Set psPlanPara2Pdf.
     *
     * @param string $planPara2PDF
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanPara2PDF($planPara2PDF)
    {
        $this->planPara2PDF = $planPara2PDF;

        return $this;
    }

    /**
     * Get psPlanPara2Pdf.
     *
     * @return string
     */
    public function getPlanPara2PDF()
    {
        return $this->planPara2PDF;
    }

    /**
     * Set psPlanDrawText.
     *
     * @param string $planDrawText
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanDrawText($planDrawText)
    {
        $this->planDrawText = $planDrawText;

        return $this;
    }

    /**
     * Get psPlanDrawText.
     *
     * @return string
     */
    public function getPlanDrawText()
    {
        return $this->planDrawText;
    }

    /**
     * Set psPlanDrawPdf.
     *
     * @param string $planDrawPDF
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanDrawPDF($planDrawPDF)
    {
        $this->planDrawPDF = $planDrawPDF;

        return $this;
    }

    /**
     * Get psPlanDrawPdf.
     *
     * @return string
     */
    public function getPlanDrawPDF()
    {
        return $this->planDrawPDF;
    }

    /**
     * Set psEmailTitle.
     *
     * @param string $emailTitle
     *
     * @return ProcedureSettingsInterface
     */
    public function setEmailTitle($emailTitle)
    {
        $this->emailTitle = $emailTitle;

        return $this;
    }

    /**
     * Get psEmailTitle.
     *
     * @return string
     */
    public function getEmailTitle()
    {
        return $this->emailTitle;
    }

    /**
     * Set psEmailText.
     *
     * @param string $emailText
     *
     * @return ProcedureSettingsInterface
     */
    public function setEmailText($emailText)
    {
        $this->emailText = $emailText;

        return $this;
    }

    /**
     * Get psEmailText.
     *
     * @return string
     */
    public function getEmailText()
    {
        return $this->emailText;
    }

    /**
     * Set psEmailCc.
     *
     * @param string $emailCc
     *
     * @return ProcedureSettingsInterface
     */
    public function setEmailCc($emailCc)
    {
        $this->emailCc = $emailCc;

        return $this;
    }

    /**
     * Get psEmailCc.
     *
     * @return string
     */
    public function getEmailCc()
    {
        return $this->emailCc;
    }

    /**
     * Set Links.
     *
     * @param string $links
     *
     * @return ProcedureInterface
     */
    public function setLinks($links)
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Get Links.
     *
     * @return string
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Set p.
     *
     * @return ProcedureSettingsInterface
     */
    public function setProcedure(?ProcedureInterface $procedure = null)
    {
        $this->procedure = $procedure;

        return $this;
    }

    /**
     * Get p.
     *
     * @return ProcedureInterface
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param string $pictogram
     *
     * @return $this
     */
    public function setPictogram($pictogram)
    {
        $this->pictogram = $pictogram;

        return $this;
    }

    public function getPictogram(): ?string
    {
        return $this->pictogram;
    }

    /**
     * Returns the internal phase to which will be switch, when the time(dateOfSwitchPhase) has come.
     *
     * @return string
     */
    public function getDesignatedPhase()
    {
        return $this->procedure->getPhaseObject()->getDesignatedPhase();
    }

    /**
     * @param string $designatedPhase
     *
     * @return $this
     */
    public function setDesignatedPhase($designatedPhase)
    {
        $this->procedure->getPhaseObject()->setDesignatedPhase($designatedPhase);

        return $this;
    }

    /**
     * Returns the external phase to which will be switch, when the time(dateOfSwitchPublicPhase) has come.
     *
     * @return string
     */
    public function getDesignatedPublicPhase()
    {
        return $this->procedure->getPublicParticipationPhaseObject()->getDesignatedPhase();
    }

    /**
     * @param string $designatedPublicPhase
     */
    public function setDesignatedPublicPhase($designatedPublicPhase): self
    {
        $this->procedure->getPublicParticipationPhaseObject()->setDesignatedPhase($designatedPublicPhase);

        return $this;
    }

    /**
     * Returns the date which is defined for switching the current phase of the procedure to the designated phase.
     * Null is a valid value in this case and indicates that no date is set.
     *
     * @return DateTime|null date, which is set | null if date not set or there are no related settings
     */
    public function getDesignatedSwitchDate(): ?DateTime
    {
        return $this->procedure->getPhaseObject()->getDesignatedSwitchDate();
    }

    public function setDesignatedSwitchDate(?DateTime $designatedSwitchDate): self
    {
        $this->procedure->getPhaseObject()->setDesignatedSwitchDate($designatedSwitchDate);

        return $this;
    }

    /**
     * Returns the date which is defined for switching the current public phase of the procedure to the designated phase.
     * Null is a valid value in this case and indicates that no date is set.
     *
     * @return DateTime|null date, which is set | null if date not set or there are no related settings
     */
    public function getDesignatedPublicSwitchDate(): ?DateTime
    {
        return $this->procedure->getPublicParticipationPhaseObject()->getDesignatedSwitchDate();
    }

    public function setDesignatedPublicSwitchDate(?DateTime $designatedPublicSwitchDate): self
    {
        $this->procedure->getPublicParticipationPhaseObject()->setDesignatedSwitchDate($designatedPublicSwitchDate);

        return $this;
    }

    /**
     * Returns the End Date to which will be switch, when the time(dateOfSwitchPhase) has come.
     */
    public function getDesignatedEndDate(): ?DateTime
    {
        return $this->procedure->getPhaseObject()->getDesignatedEndDate();
    }

    /**
     * @param DateTime $designatedEndDate
     *
     * @return $this
     */
    public function setDesignatedEndDate($designatedEndDate)
    {
        $this->procedure->getPhaseObject()->setDesignatedEndDate($designatedEndDate);

        return $this;
    }

    /**
     * Returns the End Date to which will be switch, when the time(dateOfSwitchPhase) has come.
     */
    public function getDesignatedPublicEndDate(): ?DateTime
    {
        return $this->procedure->getPublicParticipationPhaseObject()->getDesignatedEndDate();
    }

    /**
     * @return $this
     */
    public function setDesignatedPublicEndDate($designatedPublicEndDate)
    {
        $this->procedure->getPublicParticipationPhaseObject()->setDesignatedEndDate($designatedPublicEndDate);

        return $this;
    }

    /**
     * Get sendMailsToCounties.
     *
     * @return bool
     */
    public function getSendMailsToCounties()
    {
        return $this->sendMailsToCounties;
    }

    /**
     * Set sendMailsToCounties.
     *
     * @param bool $sendMailsToCounties
     */
    public function setSendMailsToCounties($sendMailsToCounties)
    {
        $this->sendMailsToCounties = $sendMailsToCounties;
    }

    public function getPlanningArea(): ?string
    {
        return $this->planningArea;
    }

    /**
     * @param string $planningArea
     *
     * @return ProcedureSettingsInterface
     */
    public function setPlanningArea($planningArea)
    {
        $this->planningArea = $planningArea;

        return $this;
    }

    /**
     * @return array
     */
    public function getScales()
    {
        if ('' == $this->scales || is_null($this->scales)) {
            return [];
        }

        return is_array($this->scales) ? $this->scales : explode(',', $this->scales);
    }

    /**
     * @param array|string $scales
     */
    public function setScales($scales)
    {
        $this->scales = is_array($scales) ? implode(',', $scales) : $scales;
    }

    /**
     * @return string
     */
    public function getLegalNotice()
    {
        return $this->legalNotice;
    }

    /**
     * @param string $legalNotice
     */
    public function setLegalNotice($legalNotice)
    {
        $this->legalNotice = $legalNotice;
    }

    /**
     * @return string
     */
    public function getCopyright()
    {
        return $this->copyright;
    }

    /**
     * @param string $copyright
     */
    public function setCopyright($copyright)
    {
        $this->copyright = $copyright;
    }

    public function getMapHint(): string
    {
        return $this->mapHint;
    }

    public function setMapHint(string $mapHint)
    {
        $this->mapHint = $mapHint;
    }

    /**
     * @return Collection<int,ProcedureInterface>
     */
    public function getAllowedSegmentAccessProcedures(): Collection
    {
        return $this->allowedSegmentAccessProcedures;
    }

    /**
     * @param Collection<int,ProcedureInterface> $allowedSegmentAccessProcedures
     */
    public function setAllowedSegmentAccessProcedures(Collection $allowedSegmentAccessProcedures): self
    {
        $this->allowedSegmentAccessProcedures = $allowedSegmentAccessProcedures;

        return $this;
    }

    public function getDesignatedPhaseChangeUser(): ?UserInterface
    {
        return $this->procedure->getPhaseObject()->getDesignatedPhaseChangeUser();
    }

    public function getDesignatedPublicPhaseChangeUser(): ?UserInterface
    {
        return $this->procedure->getPublicParticipationPhaseObject()->getDesignatedPhaseChangeUser();
    }

    public function setDesignatedPhaseChangeUser(?UserInterface $designatedPhaseChangeUser): self
    {
        $this->procedure->getPhaseObject()->setDesignatedPhaseChangeUser($designatedPhaseChangeUser);

        return $this;
    }

    public function setDesignatedPublicPhaseChangeUser(?UserInterface $designatedPublicPhaseChangeUser): self
    {
        $this->procedure->getPublicParticipationPhaseObject()
            ->setDesignatedPhaseChangeUser($designatedPublicPhaseChangeUser);

        return $this;
    }
}
