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
use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_draft_statement_versions", indexes={@ORM\Index(name="_ds_id", columns={"_ds_id"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DraftStatementVersionRepository")
 */
class DraftStatementVersion extends CoreEntity implements UuidEntityInterface, DraftStatementVersionInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_dsv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var DraftStatement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement", inversedBy="versions", )
     *
     * @ORM\JoinColumn(name="_ds_id", referencedColumnName="_ds_id", nullable=false, onDelete="CASCADE")
     */
    protected $draftStatement;

    /**
     * Virtuelle Eigenshaft der Id des DraftStatements.
     *
     * @var string
     */
    protected $dsId;

    /**
     * @var Procedure
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
    protected $number = '';

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
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", cascade={"persist"})
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
     * @var SingleDocumentVersion
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
     * @var string
     */
    protected $elementId;

    /**
     * @var Elements
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
     * todo: potential improvement: options={"fixed":false},.
     *
     * @var string
     *
     * @ORM\Column(name="_ds_file", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $file = '';

    /**
     * @var FileContainer
     *                    No doctrine connection because of multiple inheritance. Real inheritance mapping as described in
     *                    http://doctrine-orm.readthedocs.io/en/latest/reference/inheritance-mapping.html
     *                    is not possible atm, because primary keys are named differently across entities
     *                    Files have to be get via Repository
     */
    protected $files;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_map_file", type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $mapFile;

    /**
     * todo: potential improvement: options={"fixed":false},.
     *
     * @var Orga
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
     * @var string
     *
     * @ORM\Column(name="_d_name", type="string", length=255, nullable=false)
     */
    protected $dName = '';

    /**
     * @var Department
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
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", nullable=false, onDelete="RESTRICT")
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
     * todo: potential improvement: options={"default":false},.
     *
     * @var int
     *
     * @ORM\Column(name="_ds_negative_statement", type="boolean", nullable=false)
     */
    protected $negativ = 0;

    /**
     * todo: potential improvement: options={"default":false},.
     *
     * @var int
     *
     * @ORM\Column(name="_ds_submited", type="boolean", nullable=false)
     */
    protected $submitted = 0;

    /**
     * todo: potential improvement: options={"default":false},.
     *
     * @var int
     *
     * @ORM\Column(name="_ds_released", type="boolean", nullable=false)
     */
    protected $released = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_ds_show_all", type="boolean", nullable=false, options={"default":false})
     */
    protected $showToAll = 0;

    /**
     * todo: potential improvement: options={"default":false},.
     *
     * @var int
     *
     * @ORM\Column(name="_ds_deleted", type="boolean", nullable=false)
     */
    protected $deleted = 0;

    /**
     * todo: potential improvement: options={"default":false},.
     *
     * @var int
     *
     * @ORM\Column(name="_ds_rejected", type="boolean", nullable=false)
     */
    protected $rejected = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_ds_public_allowed", type="boolean", nullable=false, options={"default":false})
     */
    protected $publicAllowed = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_ds_public_use_name", type="boolean", nullable=false, options={"default":false})
     */
    protected $publicUseName = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_public_draft_statement", type="string", length=20, nullable=false)
     */
    protected $publicDraftStatement = DraftStatement::INTERNAL;

    /**
     * @var string
     *
     * @ORM\Column(name="_ds_phase", type="string", length=50, nullable=false)
     */
    protected $phase = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_version_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $versionDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_ds_created_date", type="datetime", nullable=false)
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

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @deprecated use {@link DraftStatementVersion::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * @return DraftStatement
     */
    public function getDraftStatement()
    {
        return $this->draftStatement;
    }

    /**
     * @param DraftStatement $draftStatement
     */
    public function setDraftStatement($draftStatement)
    {
        $this->draftStatement = $draftStatement;
    }

    /**
     * @return string
     */
    public function getDsId()
    {
        if (is_null($this->dsId) && $this->draftStatement instanceof DraftStatement) {
            $this->dsId = $this->draftStatement->getId();
        }

        return $this->dsId;
    }

    /**
     * Set procedure.
     *
     * @param Procedure $procedure
     *
     * @return DraftStatementVersion
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
        if ($procedure instanceof Procedure) {
            $this->pId = $procedure->getId();
        }

        return $this;
    }

    /**
     * Get procedure.
     *
     * @return Procedure
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
        if (is_null($this->pId) && $this->procedure instanceof Procedure) {
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return Paragraph|null
     */
    public function getParagraph()
    {
        return $this->paragraph;
    }

    /**
     * @param Paragraph $paragraph
     */
    public function setParagraph($paragraph)
    {
        $this->paragraph = $paragraph;
    }

    /**
     * Get paragraphId.
     *
     * @return string
     */
    public function getParagraphId()
    {
        if (is_null($this->paragraphId) && $this->paragraph instanceof Paragraph) {
            $this->paragraphId = $this->paragraph->getId();
        }

        return $this->paragraphId;
    }

    /**
     * @return SingleDocumentVersion|null
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param SingleDocumentVersion $documentVersion
     */
    public function setDocument($documentVersion)
    {
        $this->document = $documentVersion;
    }

    /**
     * Get documentId.
     *
     * @return string
     */
    public function getDocumentId()
    {
        if (is_null($this->documentId) && $this->document instanceof SingleDocumentVersion) {
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
        if (is_null($this->elementId) && $this->element instanceof Elements) {
            $this->elementId = $this->element->getId();
        }

        return $this->elementId;
    }

    /**
     * @return Elements|null
     */
    public function getElement()
    {
        return $this->element;
    }

    public function setElement($element)
    {
        $this->element = $element;
    }

    /**
     * Set polygon.
     *
     * @param string $polygon
     *
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return ArrayCollection
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param ArrayCollection $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * Set mapFile.
     *
     * @param string $mapFile
     *
     * @return DraftStatementVersion
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
     * @param Orga $organisation
     *
     * @return DraftStatementVersion
     */
    public function setOrganisation($organisation)
    {
        $this->organisation = $organisation;

        return $this;
    }

    /**
     * Get oId.
     *
     * @return Orga
     */
    public function getOrganisation()
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
        if (is_null($this->oId) && $this->organisation instanceof Orga) {
            $this->oId = $this->organisation->getId();
        }

        return $this->oId;
    }

    /**
     * Set oName.
     *
     * @param string $oName
     *
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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

    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * @param Department $department
     */
    public function setDepartment($department)
    {
        $this->department = $department;
    }

    public function getDId()
    {
        if (is_null($this->dId) && $this->department instanceof Department) {
            $this->dId = $this->department->getId();
        }

        return $this->dId;
    }

    /**
     * Set user.
     *
     * @param User $user
     *
     * @return DraftStatementVersion
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return User
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
        if (is_null($this->uId) && $this->user instanceof User) {
            $this->uId = $this->user->getId();
        }

        return $this->uId;
    }

    /**
     * Set uName.
     *
     * @param string $uName
     *
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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

    /**
     * Set feedback.
     *
     * @param string $feedback
     *
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
     */
    public function setNegativ($negativ)
    {
        $this->negativ = (int) $negativ;

        return $this;
    }

    /**
     * Get negativ.
     *
     * @return bool
     */
    public function getNegativ()
    {
        return (bool) $this->negativ;
    }

    /**
     * Set submitted.
     *
     * @param bool $submitted
     *
     * @return DraftStatementVersion
     */
    public function setSubmitted($submitted)
    {
        $this->submitted = (int) $submitted;

        return $this;
    }

    /**
     * Get submitted.
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return (bool) $this->submitted;
    }

    /**
     * Set released.
     *
     * @param bool $released
     *
     * @return DraftStatementVersion
     */
    public function setReleased($released)
    {
        $this->released = (int) $released;

        return $this;
    }

    /**
     * Get released.
     *
     * @return bool
     */
    public function isReleased()
    {
        return (bool) $this->released;
    }

    /**
     * Set showToAll.
     *
     * @param bool $showToAll
     *
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return int
     */
    public function isDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * Set rejected.
     *
     * @param bool $rejected
     *
     * @return DraftStatementVersion
     */
    public function setRejected($rejected)
    {
        $this->rejected = (int) $rejected;

        return $this;
    }

    /**
     * Get rejected.
     *
     * @return bool
     */
    public function isRejected()
    {
        return (bool) $this->rejected;
    }

    /**
     * Set publicAllowed.
     *
     * @param bool $publicAllowed
     *
     * @return DraftStatementVersion
     */
    public function setPublicAllowed($publicAllowed)
    {
        $this->publicAllowed = (int) $publicAllowed;

        return $this;
    }

    /**
     * Get publicAllowed.
     *
     * @return bool
     */
    public function isPublicAllowed()
    {
        return (bool) $this->publicAllowed;
    }

    /**
     * Set publicUseName.
     *
     * @param bool $publicUseName
     *
     * @return DraftStatementVersion
     */
    public function setPublicUseName($publicUseName)
    {
        $this->publicUseName = (int) $publicUseName;

        return $this;
    }

    /**
     * Get publicUseName.
     *
     * @return bool
     */
    public function isPublicUseName()
    {
        return (bool) $this->publicUseName;
    }

    /**
     * Set publicDraftStatement.
     *
     * @param string $publicDraftStatement
     *
     * @return DraftStatementVersion
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
     * Set phase.
     *
     * @param string $phase
     *
     * @return DraftStatementVersion
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
     * @return DateTime
     */
    public function getVersionDate()
    {
        return $this->versionDate;
    }

    /**
     * @param DateTime $versionDate
     *
     * @return DraftStatementVersion
     */
    public function setVersionDate($versionDate)
    {
        $this->versionDate = $versionDate;

        return $this;
    }

    /**
     * Set createdDate.
     *
     * @param DateTime $createdDate
     *
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
     * @return DraftStatementVersion
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
}
