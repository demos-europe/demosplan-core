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
use DemosEurope\DemosplanAddon\Contracts\Entities\ParagraphVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFragmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFragmentVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * StatementFragmentVersion - Represents a Version of a fragment of a statement.
 *
 * Statement Fragment Versions are part of the assessment process
 *
 * @ORM\Table(name="statement_fragment_version")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementFragmentVersionRepository")
 */
class StatementFragmentVersion extends CoreEntity implements UuidEntityInterface, StatementFragmentVersionInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="sfv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var StatementFragmentInterface
     *
     * todo: should be nullable = false? will not working with onDelete="SET NULL"
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment", inversedBy="versions")
     *
     * @ORM\JoinColumn(name="statement_fragment_id", referencedColumnName="sf_id", nullable=true, onDelete="SET NULL")
     */
    protected $statementFragment;

    /**
     * @var int // unsigned int auto_increment
     *
     * @ORM\Column(name="display_id", type="integer", options={"unsigned":true})
     */
    protected $displayId;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_text", type="text", nullable=false)
     */
    protected $text;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_statement_fragment_version_tag", type="text", nullable=false)
     */
    protected $tagAndTopicNames;

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
     *
     * @ORM\Column(name="sfv_vote_advice", type="text", nullable=true)
     */
    protected $voteAdvice;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_vote", type="text", nullable=true)
     */
    protected $vote;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="created_date", type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_department_name", type="text", nullable=true)
     */
    protected $departmentName;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_orga_name", type="text", nullable=true)
     */
    protected $orgaName;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_consideration_advice", type="text", nullable=true)
     */
    protected $considerationAdvice;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_consideration", type="text", nullable=true)
     */
    protected $consideration;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_county_name", type="text", nullable=false)
     */
    protected $countyNames;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_priority_area_name", type="text", nullable=false)
     */
    protected $priorityAreaKeys;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_municipality_name", type="text", nullable=false)
     */
    protected $municipalityNames;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_archived_orga_name", type="text", nullable=true)
     */
    protected $archivedOrgaName;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_archived_department_name", type="text", nullable=true)
     */
    protected $archivedDepartmentName;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_archived_vote_user_name", type="text", nullable=true)
     */
    protected $archivedVoteUserName;

    /**
     * User who triggered this Version.
     *
     * @var UserInterface
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="sfv_modified_by_u_id", referencedColumnName="_u_id", onDelete="SET NULL")
     */
    protected $modifiedByUser;

    /**
     * Department which triggered this Version.
     *
     * @var DepartmentInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department")
     *
     * @ORM\JoinColumn(name="sfv_modified_by_d_id", referencedColumnName="_d_id", onDelete="SET NULL")
     **/
    protected $modifiedByDepartment;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_element_title", type="text", nullable=true, length=2500)
     */
    protected $elementTitle;

    /**
     * @var string
     *
     * @ORM\Column(name="sfv_element_category", type="text", nullable=true, length=256)
     */
    protected $elementCategory;

    /**
     * @var ParagraphVersionInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="paragraph_id", referencedColumnName="_pdv_id", onDelete="SET NULL")
     */
    protected $paragraph;

    /**
     * @var SingleDocumentVersionInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="document_id", referencedColumnName="_sdv_id", onDelete="SET NULL")
     */
    protected $document;

    public function __construct(StatementFragment $fragmentToCreateVersionFrom)
    {
        $this->archivedDepartmentName = $fragmentToCreateVersionFrom->getArchivedDepartmentName();
        $this->archivedOrgaName = $fragmentToCreateVersionFrom->getArchivedOrgaName();
        $this->archivedVoteUserName = $fragmentToCreateVersionFrom->getArchivedVoteUserName();
        $this->consideration = $fragmentToCreateVersionFrom->getConsideration();
        $this->considerationAdvice = $fragmentToCreateVersionFrom->getConsiderationAdvice();
        $this->displayId = $fragmentToCreateVersionFrom->getDisplayId();
        $this->text = $fragmentToCreateVersionFrom->getText();
        $this->vote = $fragmentToCreateVersionFrom->getVote();
        $this->voteAdvice = $fragmentToCreateVersionFrom->getVoteAdvice();
        $this->document = $fragmentToCreateVersionFrom->getDocument();

        // kept relations:
        $this->setStatementFragment($fragmentToCreateVersionFrom);
        $this->procedure = $fragmentToCreateVersionFrom->getProcedure();

        // solved relations:
        $this->setCountyNames($fragmentToCreateVersionFrom->getCountyNames());
        $this->setMunicipalityNames($fragmentToCreateVersionFrom->getMunicipalityNames());
        $this->setPriorityAreaKeys($fragmentToCreateVersionFrom->getPriorityAreaKeys());
        $this->tagAndTopicNames = $fragmentToCreateVersionFrom->getTagsAndTopicsAsString();

        $this->departmentName = null;
        $this->orgaName = null;
        if (!is_null($fragmentToCreateVersionFrom->getDepartment())) {
            $this->departmentName = $fragmentToCreateVersionFrom->getDepartment()->getName();
            $this->orgaName = $fragmentToCreateVersionFrom->getDepartment()->getOrgaName();
        }
        $this->setModifiedByDepartment($fragmentToCreateVersionFrom->getModifiedByDepartment());
        $this->setModifiedByUser($fragmentToCreateVersionFrom->getModifiedByUser());

        $this->elementTitle = $fragmentToCreateVersionFrom->getElementTitle();
        $this->elementCategory = $fragmentToCreateVersionFrom->getElementCategory();
        $this->paragraph = $fragmentToCreateVersionFrom->getParagraph();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return StatementFragmentInterface
     */
    public function getStatementFragment()
    {
        return $this->statementFragment;
    }

    /**
     * @return $this
     */
    public function setStatementFragment(StatementFragmentInterface $relatedFragment)
    {
        $this->statementFragment = $relatedFragment;
        $relatedFragment->addVersion($this);

        return $this;
    }

    /**
     * @return int
     */
    public function getDisplayId()
    {
        return $this->displayId;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @return string
     */
    public function getTagAndTopicNames()
    {
        return $this->tagAndTopicNames;
    }

    /**
     * @return ProcedureInterface
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @return string
     */
    public function getVoteAdvice()
    {
        return $this->voteAdvice;
    }

    /**
     * @return string
     */
    public function getVote()
    {
        return $this->vote;
    }

    /**
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @return string
     */
    public function getDepartmentName()
    {
        return $this->departmentName;
    }

    /**
     * @return string
     */
    public function getConsiderationAdvice()
    {
        return $this->considerationAdvice;
    }

    /**
     * @return string
     */
    public function getConsideration()
    {
        return $this->consideration;
    }

    /**
     * @return string
     */
    public function getCountyNamesAsJson()
    {
        return $this->countyNames;
    }

    /**
     * @return string
     */
    public function getPriorityAreaKeysAsJson()
    {
        return $this->priorityAreaKeys;
    }

    /**
     * @return string
     */
    public function getPriorityAreaNamesAsJson()
    {
        return $this->getPriorityAreaKeysAsJson();
    }

    /**
     * @return string
     */
    public function getMunicipalityNamesAsJson()
    {
        return $this->municipalityNames;
    }

    /**
     * @return string
     */
    public function getArchivedOrgaName()
    {
        return $this->archivedOrgaName;
    }

    /**
     * @return string
     */
    public function getArchivedDepartmentName()
    {
        return $this->archivedDepartmentName;
    }

    /**
     * @return string
     */
    public function getArchivedVoteUserName()
    {
        return $this->archivedVoteUserName;
    }

    /**
     * @param string $archivedVoteUserName
     */
    public function setArchivedVoteUserName($archivedVoteUserName)
    {
        $this->archivedVoteUserName = $archivedVoteUserName;
    }

    /**
     * @return string
     */
    public function getOrgaName()
    {
        return $this->orgaName;
    }

    /**
     * @param string $orgaName
     *
     * @return $this
     */
    public function setOrgaName($orgaName)
    {
        $this->orgaName = $orgaName;

        return $this;
    }

    /**
     * Convert incoming array to a JSON-String.
     *
     * @param string[] $priorityAreaKeys
     */
    private function setPriorityAreaKeys(array $priorityAreaKeys)
    {
        $this->priorityAreaKeys = collect($priorityAreaKeys)->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert incoming array to a JSON-String.
     *
     * @param string[] $municipalityNames
     */
    private function setMunicipalityNames(array $municipalityNames)
    {
        $this->municipalityNames = collect($municipalityNames)->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert incoming array to a JSON-String.
     *
     * @param string[] $countyNames
     */
    private function setCountyNames(array $countyNames)
    {
        $this->countyNames = collect($countyNames)->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param int $displayId
     */
    public function setDisplayId($displayId)
    {
        $this->displayId = $displayId;
    }

    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @param string $tagAndTopicNames
     */
    public function setTagAndTopicNames($tagAndTopicNames)
    {
        $this->tagAndTopicNames = $tagAndTopicNames;
    }

    /**
     * @param ProcedureInterface $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }

    /**
     * @param string $voteAdvice
     */
    public function setVoteAdvice($voteAdvice)
    {
        $this->voteAdvice = $voteAdvice;
    }

    /**
     * @param string $vote
     */
    public function setVote($vote)
    {
        $this->vote = $vote;
    }

    /**
     * @param DateTime $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @param string $departmentName
     */
    public function setDepartmentName($departmentName)
    {
        $this->departmentName = $departmentName;
    }

    /**
     * @param string $considerationAdvice
     */
    public function setConsiderationAdvice($considerationAdvice)
    {
        $this->considerationAdvice = $considerationAdvice;
    }

    /**
     * @param string $consideration
     */
    public function setConsideration($consideration)
    {
        $this->consideration = $consideration;
    }

    /**
     * @param string $archivedOrgaName
     */
    public function setArchivedOrgaName($archivedOrgaName)
    {
        $this->archivedOrgaName = $archivedOrgaName;
    }

    /**
     * @param string $archivedDepartmentName
     */
    public function setArchivedDepartmentName($archivedDepartmentName)
    {
        $this->archivedDepartmentName = $archivedDepartmentName;
    }

    /**
     * @return UserInterface
     */
    public function getModifiedByUser()
    {
        return $this->modifiedByUser;
    }

    /**
     * @return string|null
     */
    public function getModifiedByUserId()
    {
        if ($this->modifiedByUser instanceof UserInterface) {
            return $this->modifiedByUser->getId();
        }

        return null;
    }

    /**
     * @param UserInterface $modifiedByUser
     */
    public function setModifiedByUser($modifiedByUser)
    {
        if ($modifiedByUser instanceof UserInterface) {
            $this->modifiedByUser = $modifiedByUser;
        }
    }

    /**
     * @return DepartmentInterface
     */
    public function getModifiedByDepartment()
    {
        return $this->modifiedByDepartment;
    }

    /**
     * @return string|null
     */
    public function getModifiedByDepartmentId()
    {
        if ($this->modifiedByDepartment instanceof DepartmentInterface) {
            return $this->modifiedByDepartment->getId();
        }

        return null;
    }

    /**
     * @param DepartmentInterface $modifiedByDepartment
     */
    public function setModifiedByDepartment($modifiedByDepartment)
    {
        $this->modifiedByDepartment = $modifiedByDepartment;
    }

    /**
     * Get elementTitle.
     *
     * @return string
     */
    public function getElementTitle()
    {
        return $this->elementTitle;
    }

    /**
     * @return string
     */
    public function getElementCategory()
    {
        return $this->elementCategory;
    }

    /**
     * @return ParagraphVersionInterface|null
     */
    public function getParagraph()
    {
        return $this->paragraph;
    }

    /**
     * Get paragraphTitle.
     *
     * @return string
     */
    public function getParagraphTitle()
    {
        if ($this->paragraph instanceof ParagraphVersionInterface) {
            return trim($this->paragraph->getTitle() ?? '');
        }

        return '';
    }

    /**
     * @return SingleDocumentVersionInterface
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param SingleDocumentVersionInterface $document
     */
    public function setDocument($document)
    {
        $this->document = $document;
    }

    /**
     * Get documentParentId.
     *
     * @return string
     */
    public function getDocumentParentId()
    {
        $documentId = null;
        if ($this->document instanceof SingleDocumentVersionInterface) {
            $documentId = $this->document->getSingleDocument()->getId();
        }

        return $documentId;
    }
}
