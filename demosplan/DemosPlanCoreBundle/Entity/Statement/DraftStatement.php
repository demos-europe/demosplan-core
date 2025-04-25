<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\DepartmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementFileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ParagraphVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttributeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\FormDefinitionConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_draft_statement")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository")
 *
 * @FormDefinitionConstraint()
 */
class DraftStatement extends CoreEntity implements UuidEntityInterface, DraftStatementInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_ds_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * used to either limit the visibility scope within the drafts-folder to only the author
     * or in case this value is set to false - the scope is set
     * visible for all members of the {{ @see self::$organisation }}.
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    protected bool $private = true;

    /**
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var string
     */
    protected $pId;

    /**
     * @var int
     *
     * @ORM\Column(name="_ds_number", type="integer", nullable=false, options={"default":0})
     */
    protected $number = 1000;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_title", type="string", length=4000, nullable=false)
     */
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_text", type="text", nullable=false, length=15000000)
     */
    protected $text = '';

    /**
     * @var ParagraphVersionInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", cascade={"all"})
     *
     * @ORM\JoinColumn(name="_ds_paragraph_id", referencedColumnName="_pdv_id", onDelete="SET NULL")
     */
    protected $paragraph;

    /**
     * Virtuelle Eigenschaft der ParagraphId.
     *
     * @var string
     */
    protected $paragraphId;

    /**
     * @var SingleDocumentVersionInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_ds_document_id", referencedColumnName="_sdv_id", onDelete="SET NULL")
     */
    protected $document;

    /**
     * Virtuelle Eigenschagft der DocumentId.
     *
     * @var string
     */
    protected $documentId;

    /**
     * @var ArrayCollection
     */
    protected $categories;

    /**
     * Virtuelle Eigenschaft für die ElementId.
     *
     * @var string
     */
    protected $elementId;
    /**
     * Virtuelle Eigenschaft für den ElementTitle.
     *
     * @var string
     */
    protected $elementTitle;

    /**
     * @var ElementsInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_ds_element_id", referencedColumnName="_e_id", onDelete="SET NULL")
     **/
    protected $element;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_polygon", type="text", length=65535, nullable=false)
     */
    protected $polygon = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_file", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $file = '';

    /**
     * @var Collection<int, DraftStatementFileInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementFile", mappedBy="draftStatement", orphanRemoval=true, fetch="EAGER", cascade={"persist"})
     */
    protected $files;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_map_file", type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $mapFile;

    /**
     * @var OrgaInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", nullable=false, onDelete="RESTRICT")
     */
    protected $organisation;

    /**
     * Virtuelle Eigenschaft der OrganisationId.
     *
     * @var string
     */
    protected $oId;

    /**
     * @var string
     *
     * @ORM\Column(name="_o_name", type="string", length=255, nullable=false)
     */
    protected $oName = '';

    /**
     * Virtuelle Eigenschaft des Gatewaynamens der Orga für das einfachere Indizieren.
     *
     * @var string
     */
    protected $oGatewayName;

    /**
     * @var string
     *
     * @ORM\Column(name="_d_name", type="string", length=255, nullable=false)
     */
    protected $dName = '';

    /**
     * @var DepartmentInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department")
     *
     * @ORM\JoinColumn(name="_d_id", referencedColumnName="_d_id", nullable=false, onDelete="RESTRICT")
     **/
    protected $department;

    /**
     * Virtuelle Eigenschaft mit der Id des Departments.
     *
     * @var string
     */
    protected $dId;

    /**
     * @var UserInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", nullable=false, onDelete="RESTRICT")},
     */
    protected $user;

    /**
     * Virtuelle Eigenschaft der UserId.
     *
     * @var string
     */
    protected $uId;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_name", type="string", length=255, nullable=false)
     */
    protected $uName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_street", type="string", length=255, nullable=false)
     */
    protected $uStreet = '';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $houseNumber = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_postal_code", type="string", length=6, nullable=false)
     */
    protected $uPostalCode = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_city", type="string", length=255, nullable=false)
     */
    protected $uCity = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_email", type="string", length=255, nullable=false)
     */
    protected $uEmail = '';

    /**
     * User generally wants feedback for this statement. $feedback holds the kind of desired feedback.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $uFeedback = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_feedback", type="string", length=10, nullable=false)
     */
    protected $feedback = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_extern_id", type="string", length=25, nullable=false, options={"fixed":true})
     */
    protected $externId = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_rejected_reason", type="string", length=4000, nullable=false)
     */
    protected $rejectedReason = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_negative_statement", type="boolean", nullable=false)
     */
    protected $negativ = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_submited", type="boolean", nullable=false)
     */
    protected $submitted = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_released", type="boolean", nullable=false)
     */
    protected $released = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_show_all", type="boolean", nullable=false, options={"default":false})
     */
    protected $showToAll = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_deleted", type="boolean", nullable=false)
     */
    protected $deleted = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_rejected", type="boolean", nullable=false)
     */
    protected $rejected = false;

    /**
     * Darf die Stellungnahme auf der Beteiligungsebene angezeigt werden?
     *
     * @var bool
     *
     * @ORM\Column(name="_ds_public_allowed", type="boolean", nullable=false, options={"default":false})
     */
    protected $publicAllowed = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ds_public_use_name", type="boolean", nullable=false, options={"default":false})
     */
    protected $publicUseName = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_public_draft_statement", type="string", length=20, nullable=false)
     */
    protected $publicDraftStatement = DraftStatementInterface::INTERNAL;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_represents", type="string", length=256, nullable=true, options={"default":""})
     */
    protected $represents = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_phase", type="string", length=50, nullable=false)
     */
    protected $phase = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_deleted_date", type="datetime", nullable=false)
     */
    protected $deletedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_last_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $lastModifiedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_submited_date", type="datetime", nullable=false)
     */
    protected $submittedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_released_date", type="datetime", nullable=false)
     */
    protected $releasedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_rejected_date", type="datetime", nullable=false)
     */
    protected $rejectedDate;

    /**
     * @var ProcedureInterface
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion", mappedBy="draftStatement")
     */
    protected $versions;

    /**
     * @var Collection<int, StatementAttributeInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute", mappedBy="draftStatement")
     */
    protected $statementAttributes;

    /**
     * @var array
     *
     * @ORM\Column(name="_ds_misc_data", type="array", nullable=true)
     */
    protected $miscData;

    /**
     * True in case of the draft-statement was given anonymously.
     * (This is currently only possible as unregistered guest user in public detail).
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable = false, options={"default":false})
     */
    private $anonymous = false;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->versions = new ArrayCollection();
        $this->deletedDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->submittedDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->releasedDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->rejectedDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->statementAttributes = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->miscData = [];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @deprecated use {@link DraftStatement::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): void
    {
        $this->private = $private;
    }

    /**
     * Set procedure.
     *
     * @param ProcedureInterface $procedure
     *
     * @return DraftStatementInterface
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
        if ($procedure instanceof ProcedureInterface) {
            $this->pId = $procedure->getId();
        }

        return $this;
    }

    /**
     * Get procedure.
     *
     * @return ProcedureInterface
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * Get pId.
     *
     * @return string
     */
    public function getPId()
    {
        if ($this->procedure instanceof ProcedureInterface) {
            $this->pId = $this->procedure->getId();
        }

        return $this->pId;
    }

    /**
     * Get ProcedureId.
     *
     * @return string
     */
    public function getProcedureId()
    {
        return $this->getPId();
    }

    /**
     * Set number.
     *
     * @param int $number
     *
     * @return DraftStatementInterface
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get number.
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return DraftStatementInterface
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return ParagraphVersionInterface|null
     */
    public function getParagraph()
    {
        return $this->paragraph;
    }

    /**
     * @param ParagraphVersionInterface|null $paragraph
     */
    public function setParagraph($paragraph)
    {
        $this->paragraph = $paragraph;

        // setze die ggf. zwischengespeicherten Daten zurück
        $this->paragraphId = null;
    }

    /**
     * Get paragraphId.
     *
     * @return string
     */
    public function getParagraphId()
    {
        if ($this->paragraph instanceof ParagraphVersionInterface) {
            $this->paragraphId = $this->paragraph->getId();
        }

        return $this->paragraphId;
    }

    /**
     * @return SingleDocumentVersionInterface|null
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param SingleDocumentVersionInterface|null $documentVersion
     */
    public function setDocument($documentVersion)
    {
        $this->document = $documentVersion;
        // setze die ggf. zwischengespeicherten Daten zurück
        $this->documentId = null;
    }

    /**
     * Get documentId.
     *
     * @return string
     */
    public function getDocumentId()
    {
        if ($this->document instanceof SingleDocumentVersionInterface) {
            $this->documentId = $this->document->getId();
        }

        return $this->documentId;
    }

    /**
     * Get elementId.
     *
     * @return string
     */
    public function getElementId()
    {
        if ($this->element instanceof ElementsInterface) {
            $this->elementId = $this->element->getId();
        }

        return $this->elementId;
    }

    /**
     * Get elementTitle.
     *
     * @return string
     */
    public function getElementTitle()
    {
        if ($this->element instanceof ElementsInterface) {
            $this->elementTitle = $this->element->getTitle();
        }

        return $this->elementTitle;
    }

    /**
     * @return ElementsInterface|null
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param ElementsInterface|null $element
     */
    public function setElement($element)
    {
        $this->element = $element;

        // setze die ggf. zwischengespeicherten Daten zurück
        $this->elementId = null;
        $this->elementTitle = null;
    }

    /**
     * Set polygon.
     *
     * @param string $polygon
     *
     * @return DraftStatementInterface
     */
    public function setPolygon($polygon)
    {
        $this->polygon = $polygon;

        return $this;
    }

    /**
     * Get polygon.
     *
     * @return string
     */
    public function getPolygon()
    {
        return $this->polygon;
    }

    /**
     * Set file.
     *
     * @param string $file
     *
     * @return DraftStatementInterface
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file.
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Return FileStrings to keep method backwards compatible.
     *
     * @return array<int,string|null>
     */
    public function getFiles(): array
    {
        return $this->files->map(static fn (DraftStatementFileInterface $draftStatementFile): ?string => $draftStatementFile->getFileString())->toArray();
    }

    public function addFile(DraftStatementFileInterface $draftStatementFile): self
    {
        if (!$this->files->contains($draftStatementFile)) {
            $this->files[] = $draftStatementFile;
            $draftStatementFile->setDraftStatement($this);
        }

        return $this;
    }

    public function removeFile(DraftStatementFileInterface $draftStatementFile): self
    {
        if ($this->files->removeElement($draftStatementFile)) {
            // set the owning side to null (unless already changed)
            if ($draftStatementFile->getDraftStatement() === $this) {
                // set to null to activate orphan removal
                $draftStatementFile->setDraftStatement(null);
            }
        }

        return $this;
    }

    public function removeFileByFileId(string $fileId): self
    {
        foreach ($this->files as $file) {
            if ($file->getFile()->getId() !== $fileId) {
                continue;
            }

            return $this->removeFile($file);
        }

        return $this;
    }

    /**
     * Set mapFile.
     *
     * @param string $mapFile
     *
     * @return DraftStatementInterface
     */
    public function setMapFile($mapFile)
    {
        $this->mapFile = $mapFile;

        return $this;
    }

    /**
     * Get mapFile.
     *
     * @return string
     */
    public function getMapFile()
    {
        return $this->mapFile;
    }

    /**
     * Set oId.
     *
     * @param OrgaInterface $organisation
     *
     * @return DraftStatementInterface
     */
    public function setOrganisation($organisation)
    {
        $this->organisation = $organisation;

        return $this;
    }

    public function getOrganisation(): OrgaInterface
    {
        return $this->organisation;
    }

    /**
     * Get Organisation Id.
     *
     * @return string
     */
    public function getOId()
    {
        if ($this->organisation instanceof OrgaInterface) {
            $this->oId = $this->organisation->getId();
        }

        return $this->oId;
    }

    /**
     * Get Organisation GatewayName.
     *
     * @return string
     */
    public function getOGatewayName()
    {
        if ($this->organisation instanceof OrgaInterface) {
            try {
                $this->oGatewayName = $this->organisation->getGatewayName();
            } catch (Exception) {
                $this->oGatewayName = '';
            }
        }

        return $this->oGatewayName;
    }

    /**
     * Set oName.
     *
     * @param string $oName
     *
     * @return DraftStatementInterface
     */
    public function setOName($oName)
    {
        $this->oName = $oName;

        return $this;
    }

    /**
     * Get oName.
     *
     * @return string
     */
    public function getOName()
    {
        return $this->oName;
    }

    /**
     * Set dName.
     *
     * @param string $dName
     *
     * @return DraftStatementInterface
     */
    public function setDName($dName)
    {
        $this->dName = $dName;

        return $this;
    }

    /**
     * Get dName.
     *
     * @return string
     */
    public function getDName()
    {
        return $this->dName;
    }

    /**
     * @return mixed|DepartmentInterface
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * @param DepartmentInterface $department
     */
    public function setDepartment($department)
    {
        $this->department = $department;
    }

    public function getDId()
    {
        if ($this->department instanceof DepartmentInterface) {
            $this->dId = $this->department->getId();
        }

        return $this->dId;
    }

    /**
     * Set user.
     *
     * @param UserInterface $user
     *
     * @return DraftStatementInterface
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return UserInterface
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getUId()
    {
        if ($this->user instanceof UserInterface) {
            $this->uId = $this->user->getId();
        }

        return $this->uId;
    }

    /**
     * Set uName.
     *
     * @param string $uName
     *
     * @return DraftStatementInterface
     */
    public function setUName($uName)
    {
        $this->uName = $uName;

        return $this;
    }

    /**
     * Get uName.
     *
     * @return string
     */
    public function getUName()
    {
        return $this->uName;
    }

    /**
     * Set uStreet.
     *
     * @param string $uStreet
     *
     * @return DraftStatementInterface
     */
    public function setUStreet($uStreet)
    {
        $this->uStreet = $uStreet;

        return $this;
    }

    /**
     * Get uStreet.
     *
     * @return string
     */
    public function getUStreet()
    {
        return $this->uStreet;
    }

    public function getCategories()
    {
        if ($this->categories instanceof Collection) {
            return $this->categories->toArray();
        }

        return [];
    }

    public function setCategories($categories)
    {
        $this->categories = $categories;
    }

    /**
     * Reset Categories.
     */
    public function clearCategories()
    {
        if (is_null($this->categories)) {
            $this->categories = new ArrayCollection();
        }
        $this->categories->clear();
    }

    /**
     * Set uPostalCode.
     *
     * @param string $uPostalCode
     *
     * @return DraftStatementInterface
     */
    public function setUPostalCode($uPostalCode)
    {
        $this->uPostalCode = $uPostalCode;

        return $this;
    }

    /**
     * Get uPostalCode.
     *
     * @return string
     */
    public function getUPostalCode()
    {
        return $this->uPostalCode;
    }

    /**
     * Set uCity.
     *
     * @param string $uCity
     *
     * @return DraftStatementInterface
     */
    public function setUCity($uCity)
    {
        $this->uCity = $uCity;

        return $this;
    }

    /**
     * Get uCity.
     *
     * @return string
     */
    public function getUCity()
    {
        return $this->uCity;
    }

    /**
     * Set uEmail.
     *
     * @param string $uEmail
     *
     * @return DraftStatementInterface
     */
    public function setUEmail($uEmail)
    {
        $this->uEmail = $uEmail;

        return $this;
    }

    /**
     * Get uEmail.
     *
     * @return string
     */
    public function getUEmail()
    {
        return $this->uEmail;
    }

    public function setUFeedback(bool $uFeedback): self
    {
        $this->uFeedback = $uFeedback;

        return $this;
    }

    public function getUFeedback(): bool
    {
        return $this->uFeedback;
    }

    /**
     * Set feedback.
     *
     * @param string $feedback
     *
     * @return DraftStatementInterface
     */
    public function setFeedback($feedback)
    {
        $this->feedback = $feedback;

        return $this;
    }

    /**
     * Get feedback.
     *
     * @return string
     */
    public function getFeedback()
    {
        return $this->feedback;
    }

    /**
     * Set externId.
     *
     * @param string $externId
     *
     * @return DraftStatementInterface
     */
    public function setExternId($externId)
    {
        $this->externId = $externId;

        return $this;
    }

    /**
     * Get externId.
     *
     * @return string
     */
    public function getExternId()
    {
        return $this->externId;
    }

    /**
     * Set rejectedReason.
     *
     * @param string $rejectedReason
     *
     * @return DraftStatementInterface
     */
    public function setRejectedReason($rejectedReason)
    {
        $this->rejectedReason = $rejectedReason;

        return $this;
    }

    /**
     * Get rejectedReason.
     *
     * @return string
     */
    public function getRejectedReason()
    {
        return $this->rejectedReason;
    }

    /**
     * Set negativ.
     *
     * @param bool $negativ
     *
     * @return DraftStatementInterface
     */
    public function setNegativ($negativ)
    {
        $this->negativ = \filter_var($negativ, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get negativ.
     *
     * @return bool
     */
    public function getNegativ()
    {
        return \filter_var($this->negativ, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set submitted.
     *
     * @param bool $submitted
     *
     * @return DraftStatementInterface
     */
    public function setSubmitted($submitted)
    {
        $this->submitted = \filter_var($submitted, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get submitted.
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return \filter_var($this->submitted, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set released.
     *
     * @param bool $released
     *
     * @return DraftStatementInterface
     */
    public function setReleased($released)
    {
        $this->released = \filter_var($released, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get released.
     *
     * @return bool
     */
    public function isReleased()
    {
        return \filter_var($this->released, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set showToAll.
     *
     * @param bool $showToAll
     *
     * @return DraftStatementInterface
     */
    public function setShowToAll($showToAll)
    {
        $this->showToAll = (int) $showToAll;

        return $this;
    }

    /**
     * Get showToAll.
     *
     * @return bool
     */
    public function isShowToAll()
    {
        return (bool) $this->showToAll;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return DraftStatementInterface
     */
    public function setDeleted($deleted)
    {
        $this->deleted = \filter_var($deleted, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get deleted.
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
        try {
            if ($this->isDeleted()) {
                return false;
            }
            if ($this->getProcedure()->isDeleted()) {
                return false;
            }

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Set rejected.
     *
     * @param bool $rejected
     *
     * @return DraftStatementInterface
     */
    public function setRejected($rejected)
    {
        $this->rejected = \filter_var($rejected, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get rejected.
     *
     * @return bool
     */
    public function isRejected()
    {
        return \filter_var($this->rejected, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set publicAllowed.
     *
     * @param bool $publicAllowed
     *
     * @return DraftStatementInterface
     */
    public function setPublicAllowed($publicAllowed)
    {
        $this->publicAllowed = \filter_var($publicAllowed, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    public function isPublicAllowed(): bool
    {
        return \filter_var($this->publicAllowed, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set publicUseName.
     *
     * @param bool $publicUseName
     *
     * @return DraftStatementInterface
     */
    public function setPublicUseName($publicUseName)
    {
        $this->publicUseName = \filter_var($publicUseName, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get publicUseName.
     *
     * @return bool
     */
    public function isPublicUseName()
    {
        return \filter_var($this->publicUseName, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set publicDraftStatement.
     *
     * @param string $publicDraftStatement
     *
     * @return DraftStatementInterface
     */
    public function setPublicDraftStatement($publicDraftStatement)
    {
        $this->publicDraftStatement = $publicDraftStatement;

        return $this;
    }

    /**
     * Get publicDraftStatement.
     *
     * @return string
     */
    public function getPublicDraftStatement()
    {
        return $this->publicDraftStatement;
    }

    /**
     * Returns a text that describes in the name of whom this
     * statement is going to be submitted.
     *
     * @return string
     */
    public function getRepresents()
    {
        return $this->represents;
    }

    /**
     * Sets a text that describes in the name of whom this
     * statement is going to be submitted.
     *
     * @param string $represents
     *
     * @return DraftStatementInterface
     */
    public function setRepresents($represents)
    {
        $this->represents = $represents;

        return $this;
    }

    /**
     * Set phase.
     *
     * @param string $phase
     *
     * @return DraftStatementInterface
     */
    public function setPhase($phase)
    {
        $this->phase = $phase;

        return $this;
    }

    /**
     * Get phase.
     *
     * @return string
     */
    public function getPhase()
    {
        return $this->phase;
    }

    /**
     * Set createdDate.
     *
     * @param DateTime $createdDate
     *
     * @return DraftStatementInterface
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * Get createdDate.
     *
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * Set deletedDate.
     *
     * @param DateTime $deletedDate
     *
     * @return DraftStatementInterface
     */
    public function setDeletedDate($deletedDate)
    {
        $this->deletedDate = $deletedDate;

        return $this;
    }

    /**
     * Get deletedDate.
     *
     * @return DateTime
     */
    public function getDeletedDate()
    {
        return $this->deletedDate;
    }

    /**
     * Set lastModifiedDate.
     *
     * @param DateTime $lastModifiedDate
     *
     * @return DraftStatementInterface
     */
    public function setLastModifiedDate($lastModifiedDate)
    {
        $this->lastModifiedDate = $lastModifiedDate;

        return $this;
    }

    /**
     * Get lastModifiedDate.
     *
     * @return DateTime
     */
    public function getLastModifiedDate()
    {
        return $this->lastModifiedDate;
    }

    /**
     * Set submittedDate.
     *
     * @param DateTime $submittedDate
     *
     * @return DraftStatementInterface
     */
    public function setSubmittedDate($submittedDate)
    {
        $this->submittedDate = $submittedDate;

        return $this;
    }

    /**
     * Get submittedDate.
     *
     * @return DateTime
     */
    public function getSubmittedDate()
    {
        return $this->submittedDate;
    }

    /**
     * Set releasedDate.
     *
     * @param DateTime $releasedDate
     *
     * @return DraftStatementInterface
     */
    public function setReleasedDate($releasedDate)
    {
        $this->releasedDate = $releasedDate;

        return $this;
    }

    /**
     * Get releasedDate.
     *
     * @return DateTime
     */
    public function getReleasedDate()
    {
        return $this->releasedDate;
    }

    /**
     * Set rejectedDate.
     *
     * @param DateTime $rejectedDate
     *
     * @return DraftStatementInterface
     */
    public function setRejectedDate($rejectedDate)
    {
        $this->rejectedDate = $rejectedDate;

        return $this;
    }

    /**
     * Get rejectedDate.
     *
     * @return DateTime
     */
    public function getRejectedDate()
    {
        return $this->rejectedDate;
    }

    /**
     * Get statementAttributes.
     *
     * @return StatementAttributeInterface[]
     */
    public function getStatementAttributes()
    {
        return $this->statementAttributes;
    }

    /**
     * Reset statementAttributes.
     */
    public function clearStatementAttributes()
    {
        $this->statementAttributes->clear();
    }

    /**
     * Get statementAttributes of one type.
     *
     * @param string $type
     *
     * @return array|string
     */
    public function getStatementAttributesByType($type)
    {
        $ret = [];
        foreach ($this->getStatementAttributes() as $sa) {
            if ($sa->getType() == $type) {
                $ret[] = $sa->getValue();
            }
        }
        if (1 === count($ret)) {
            return $ret[0];
        }

        return $ret;
    }

    /**
     * Add StatementAttribute to DraftStatement.
     */
    public function addStatementAttribute(StatementAttributeInterface $statementAttribute)
    {
        if (!$this->statementAttributes->contains($statementAttribute)) {
            $this->statementAttributes->add($statementAttribute);
        }
    }

    /**
     * Remove StatementAttribute from DraftStatement.
     */
    public function removeStatementAttribute(StatementAttributeInterface $statementAttribute)
    {
        if ($this->statementAttributes->contains($statementAttribute)) {
            $this->statementAttributes->removeElement($statementAttribute);
        }
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getMiscDataValue($key)
    {
        if (is_array($this->miscData) && array_key_exists($key, $this->miscData)) {
            return $this->miscData[$key];
        }

        return null;
    }

    /**
     * @param string $key
     *
     * @return DraftStatementInterface
     */
    public function setMiscDataValue($key, $value)
    {
        $this->miscData[$key] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getMiscData()
    {
        return $this->miscData;
    }

    /**
     * @param string $miscData
     */
    public function setMiscData($miscData)
    {
        $this->miscData = $miscData;
    }

    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(string $houseNumber)
    {
        $this->houseNumber = $houseNumber;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function setAnonymous(bool $anonymous): void
    {
        $this->anonymous = $anonymous;
    }
}
