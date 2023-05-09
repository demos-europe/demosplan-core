<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * StatementFragment - Represents a fragment of a statement.
 *
 * Statement Fragments are part of the assessment process
 * and used to sort, search and filter the multiple arguments
 * contained in a statement into a more workable version.
 *
 * @ORM\Table(
 *     name="statement_fragment",
 *     uniqueConstraints={
 *
 *         @ORM\UniqueConstraint(
 *             name="statement_fragment_unique_sort_index",
 *             columns={"statement_id", "sort_index"}
 *         )
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementFragmentRepository")
 */
class StatementFragment extends CoreEntity implements UuidEntityInterface
{
    public const VALIDATION_GROUP_MANDATORY = 'mandatory';

    /**
     * @var string|null
     *
     * @ORM\Column(name="sf_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="fragments")
     *
     * @ORM\JoinColumn(name="statement_id", referencedColumnName="_st_id", onDelete="CASCADE", nullable=false)
     */
    protected $statement;

    /**
     * @var int // unsigned int auto_increment
     *
     * @ORM\Column(name="display_id", type="integer", options={"unsigned":true})
     */
    protected $displayId;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_text", type="text", length=16777215, nullable=false)
     */
    protected $text;

    /**
     * @var Collection<int, Tag>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Tag")
     *
     * @ORM\JoinTable(
     *     name="statement_fragment_tag",
     *     joinColumns={@ORM\JoinColumn(name="sf_id", referencedColumnName="sf_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="tag_id", referencedColumnName="_t_id")}
     * )
     */
    protected $tags;

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
     *
     * @ORM\Column(name="sf_vote_advice", type="text", nullable=true)
     */
    protected $voteAdvice;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_vote", type="text", nullable=true)
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
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="modified_date", type="datetime", nullable=false)
     */
    protected $modified;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="assigned_to_fb_date", type="datetime", nullable=true)
     */
    protected $assignedToFbDate;

    /**
     * @var Department
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department")
     *
     * @ORM\JoinColumn(name="_d_id", referencedColumnName="_d_id", nullable=true, onDelete="SET NULL")
     */
    protected $department;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_consideration_advice", type="text", nullable=true)
     */
    protected $considerationAdvice;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_consideration", type="text", nullable=true)
     */
    protected $consideration;

    /**
     * @var Collection<int, County>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\County", inversedBy="statementFragments", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_statement_fragment_county",
     *     joinColumns={@ORM\JoinColumn(name="sf_id", referencedColumnName="sf_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id")}
     * )
     */
    protected $counties;

    /**
     * @var Collection<int, PriorityArea>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea", inversedBy="statementFragments", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_statement_fragment_priority_area",
     *     joinColumns={@ORM\JoinColumn(name="sf_id", referencedColumnName="sf_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_pa_id", referencedColumnName="_pa_id")}
     * )
     */
    protected $priorityAreas;

    /**
     * @var Collection<int, Municipality>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality", inversedBy="statementFragments", cascade={"persist"})
     *
     * @ORM\JoinTable(
     *     name="_statement_fragment_municipality",
     *     joinColumns={@ORM\JoinColumn(name="sf_id", referencedColumnName="sf_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_m_id", referencedColumnName="_m_id")}
     * )
     */
    protected $municipalities;

    /**
     * @var Department
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department")
     *
     * @ORM\JoinColumn(name="_archived_d_id", referencedColumnName="_d_id", nullable=true)
     */
    protected $archivedDepartment;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_archived_orga_name", type="text", nullable=true)
     */
    protected $archivedOrgaName;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_archived_department_name", type="text", nullable=true)
     */
    protected $archivedDepartmentName;

    /**
     * @var string
     *
     * @ORM\Column(name="sf_archived_vote_user_name", type="text", nullable=true)
     */
    protected $archivedVoteUserName;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="assignee", referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     * This is the user that is currently assigned to this fragment. Assigned users are
     * exclusively permitted to change fragments
     */
    protected $assignee;

    /**
     * @var Collection<int,StatementFragmentVersion>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragmentVersion", mappedBy="statementFragment")
     *
     * @ORM\OrderBy({"created" = "DESC"})
     */
    protected $versions;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"default":-1})
     *
     * @Assert\PositiveOrZero(groups={"mandatory"})
     */
    protected $sortIndex = -1;

    /**
     * User who triggered this Version.
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="modified_by_u_id", referencedColumnName="_u_id", onDelete="SET NULL")
     */
    protected $modifiedByUser;

    /**
     * @var Department
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department")
     *
     * @ORM\JoinColumn(name="modified_by_d_id", referencedColumnName="_d_id", onDelete="SET NULL")
     **/
    protected $modifiedByDepartment;

    /**
     * @var string
     *
     * @ORM\Column(type = "string", nullable = true, options={"default":"fragment.status.new"})
     */
    protected $status = 'fragment.status.new';

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="last_claimed", referencedColumnName="_u_id", onDelete="SET NULL")
     */
    protected $lastClaimed = null;

    /**
     * Virtuelle Eigenschaft für die ElementId.
     *
     * @var string
     */
    protected $elementId;

    /**
     * Virtuelle Eigenschaft für die ElementTitle.
     *
     * @var string
     */
    protected $elementTitle;

    /**
     * @var Elements
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="element_id", referencedColumnName="_e_id", onDelete="SET NULL")
     **/
    protected $element;

    /**
     * @var ParagraphVersion
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="paragraph_id", referencedColumnName="_pdv_id", onDelete="SET NULL")
     */
    protected $paragraph;

    /**
     * @var SingleDocumentVersion
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="document_id", referencedColumnName="_sdv_id", onDelete="SET NULL")
     */
    protected $document;

    /**
     * Virtuelle Eigenschaft der ParagraphId.
     *
     * @var string
     */
    protected $paragraphId;

    /**
     * Virtuelle Eigenschaft der ParagraphTitle.
     *
     * @var string
     */
    protected $paragraphTitle;
    /**
     * Virtuelle Eigenschaft der Order des Paragraphs zur Sortierung der Absätze.
     *
     * @var int
     */
    protected $paragraphOrder;

    /**
     * Virtuelle Eigenschaft der Id des ElternParagraphs zur Sortierung der Absätze.
     *
     * @var string
     */
    protected $paragraphParentId;

    /**
     * Title of the parent paragraph (paragraph of the paragraph version) as virtual property.
     *
     * @var string
     */
    protected $paragraphParentTitle;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->counties = new ArrayCollection();
        $this->priorityAreas = new ArrayCollection();
        $this->municipalities = new ArrayCollection();
        $this->versions = new ArrayCollection();
    }

    /**
     * @return Statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @param Statement $statement
     *
     * @return StatementFragment
     */
    public function setStatement($statement)
    {
        $this->statement = $statement;

        return $this;
    }

    /**
     * Get statementId for easier use in Elasticsearch.
     *
     * @return string
     */
    public function getStatementId()
    {
        if (!$this->statement instanceof Statement) {
            return null;
        }

        return $this->statement->getId();
    }

    /**
     * @return int
     */
    public function getDisplayIdRaw()
    {
        return $this->displayId;
    }

    /**
     * Pad DisplayId with 0, as Elasticsearch could not sort desc in the ancient version
     * we have to use.
     *
     * @return string
     */
    public function getDisplayId()
    {
        return str_pad($this->displayId, 5, 0, STR_PAD_LEFT);
    }

    /**
     * @param int $displayId
     */
    public function setDisplayId($displayId)
    {
        $this->displayId = $displayId;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     *
     * @return StatementFragment
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Note: Please search via string search to find usage of method.
     *
     * @return string[]
     */
    public function getTagIds()
    {
        $result = [];
        foreach ($this->getTags() as $tag) {
            $result[] = $tag->getId();
        }

        return $result;
    }

    /**
     * Returns the names of all Tags assigned to this Statement.
     *
     * @return array()
     */
    public function getTagNames()
    {
        $ret = [];
        foreach ($this->getTags() as $tag) {
            $ret[] = $tag->getTitle();
        }

        return $ret;
    }

    /**
     * @return string
     */
    public function getTagsAndTopicsAsString()
    {
        return $this->getTagsAndTopics()->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Return all Tags, used by this Fragment, ordered under the related Topic in a flat Form (Names).
     *
     * @return \Tightenco\Collect\Support\Collection
     *                                               Format:
     *                                               [
     *                                               'NameOfTopic1':
     *                                               [
     *                                               'NameOfTag1',
     *                                               'NameOfTag2',
     *                                               'NameOfTag3'
     *                                               ]
     *                                               'NameOfTopic2':
     *                                               [
     *                                               'NameOfTag3',
     *                                               'NameOfTag4',
     *                                               ]
     *                                               ]
     */
    public function getTagsAndTopics()
    {
        $tags = $this->getTags();
        $topicsWithTags = new \Tightenco\Collect\Support\Collection();

        /** @var Tag $tag */
        foreach ($tags as $tag) {
            $topicName = $tag->getTopicTitle();
            $tagName = $tag->getTitle();
            if (false == $topicsWithTags->has($topicName)) {
                $topicsWithTags->put($topicName, collect([$tagName]));
            } else {
                $topicsWithTags->get($topicName)->push($tagName);
            }
        }

        return $topicsWithTags;
    }

    /**
     * Return all Tags, used by this Fragment, ordered under the related Topic in a detailed Form.
     *
     * @return \Tightenco\Collect\Support\Collection
     *                                               Format:
     *                                               [
     *                                               'IdOfTopic1':
     *                                               [
     *                                               id = 'IdOfTopic1',
     *                                               title = 'TitleOfTopic1'
     *                                               tags =
     *                                               [
     *                                               'IdOfTag1' =
     *                                               [
     *                                               id = 'IdOfTag1',
     *                                               title = 'TitleOfTag1'
     *                                               ],
     *                                               'IdOfTag2' =
     *                                               [
     *                                               id = 'IdOfTag2',
     *                                               title = 'TitleOfTag2'
     *                                               ],
     *                                               'IdOfTag3' =
     *                                               [
     *                                               id = 'IdOfTag3',
     *                                               title = 'TitleOfTag3'
     *                                               ]
     *                                               ]
     *                                               ]
     *                                               'IdOfTopic2':
     *                                               [
     *                                               id = 'IdOfTopic2',
     *                                               title = 'TitleOfTopic2'
     *                                               tags =
     *                                               [
     *                                               'IdOfTag4' =
     *                                               [
     *                                               id = 'IdOfTag4',
     *                                               title = 'TitleOfTag4'
     *                                               ],
     *                                               'IdOfTag5' =
     *                                               [
     *                                               id = 'IdOfTag5',
     *                                               title = 'TitleOfTag5'
     *                                               ]
     *                                               ]
     *                                               ]
     *                                               ]
     */
    public function getTopics()
    {
        $topics = new \Tightenco\Collect\Support\Collection();
        $tags = $this->getTags();
        /** @var Tag $tag */
        foreach ($tags as $tag) {
            $topicId = $tag->getTopic()->getId();
            $topicName = $tag->getTopicTitle();
            if (false === $topics->has($topicId)) {
                $topics->put($topicId, collect(['id' => $topicId, 'title' => $topicName, 'tags' => collect()]));
            }
            $tags2 = $topics->get($topicId)->get('tags');
            $tags2->put($tag->getId(), ['id' => $tag->getId(), 'title' => $tag->getTitle()]);
        }

        return $topics;
    }

    /**
     * @param ArrayCollection|array $tags
     *
     * @return StatementFragment
     */
    public function setTags($tags)
    {
        $this->tags = new ArrayCollection($tags);

        return $this;
    }

    /**
     * Adds a tag to this statement fragment.
     *
     * @param Tag $tag
     */
    public function addTag(Tag $tag)
    {
        if ($this->tags instanceof Collection && !$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    /**
     * Removes a tag to this statement fragment.
     *
     * @param Tag $tag
     */
    public function removeTag(Tag $tag)
    {
        if ($this->tags instanceof Collection && $this->tags->contains($tag)) {
            $this->tags->removeElement($tag);
        }
    }

    /**
     * Set procedure.
     *
     * @param Procedure $procedure
     *
     * @return StatementFragment
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;

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
     * Get procedureId for easier use in Elasticsearch.
     *
     * @return string
     */
    public function getProcedureId()
    {
        if (!$this->procedure instanceof Procedure) {
            return null;
        }

        return $this->procedure->getId();
    }

    /**
     * Get procedureName for easier use in Elasticsearch.
     *
     * @return string
     */
    public function getProcedureName()
    {
        if (!$this->procedure instanceof Procedure) {
            return null;
        }

        return $this->procedure->getName();
    }

    /**
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @return DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Needed to create a StatementFragment from StatementFragmentDataObject.
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getVoteAdvice()
    {
        // aggregate null and emptystring
        if ('' === $this->voteAdvice) {
            return null;
        }

        return $this->voteAdvice;
    }

    /**
     * @param string|null $voteAdvice
     */
    public function setVoteAdvice($voteAdvice): self
    {
        $this->voteAdvice = $voteAdvice;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getVote()
    {
        // aggregate null and emptystring
        if ('' === $this->vote) {
            return null;
        }

        return $this->vote;
    }

    /**
     * @param string|null $vote
     */
    public function setVote($vote): self
    {
        $this->vote = $vote;

        return $this;
    }

    /**
     * @return Department
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * @param Department|null $department
     *
     * @return $this
     */
    public function setDepartment($department)
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Return DepartmentId for easier use in Elasticsearch.
     *
     * @return string|null
     */
    public function getDepartmentId()
    {
        if (!$this->department instanceof Department) {
            return null;
        }

        return $this->department->getId();
    }

    /**
     * @return string|null
     */
    public function getConsiderationAdvice()
    {
        return $this->considerationAdvice;
    }

    /**
     * @param string|null $considerationAdvice
     */
    public function setConsiderationAdvice($considerationAdvice): self
    {
        $this->considerationAdvice = $considerationAdvice;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getConsideration()
    {
        return $this->consideration;
    }

    /**
     * @param string|null $consideration
     */
    public function setConsideration($consideration): self
    {
        $this->consideration = $consideration;

        return $this;
    }

    public function addConsiderationParagraph(string $additionalConsiderationParagraphText)
    {
        $oldConsiderationText = $this->getConsideration();
        $newConsiderationText = $oldConsiderationText.$additionalConsiderationParagraphText;
        $this->setConsideration($newConsiderationText);
    }

    /**
     * @return ArrayCollection
     */
    public function getCounties()
    {
        return $this->counties;
    }

    /**
     * Returns an array of ids.
     * Note: Please search via string search to find usage of method.
     *
     * @return string[]
     */
    public function getCountyIds()
    {
        $result = [];
        foreach ($this->getCounties() as $county) {
            $result[] = $county->getId();
        }

        return $result;
    }

    /**
     * Returns an array of names.
     *
     * @return string[]
     */
    public function getCountyNames()
    {
        $result = [];
        foreach ($this->getCounties() as $county) {
            $result[] = $county->getName();
        }

        return $result;
    }

    /**
     * @param ArrayCollection|County[] $counties
     *
     * @return $this
     */
    public function setCounties($counties)
    {
        $this->counties = new ArrayCollection($counties);

        return $this;
    }

    /**
     * Add County.
     *
     * @param County $county
     */
    public function addCounty($county)
    {
        if (!$this->counties->contains($county)) {
            $this->counties->add($county);
            $county->addStatementFragment($this);
        }
    }

    /**
     * Remove County.
     *
     * @param County $county
     */
    public function removeCounty($county)
    {
        if ($this->counties->contains($county)) {
            $this->counties->removeElement($county);
            $county->removeStatementFragment($this);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getPriorityAreas()
    {
        return $this->priorityAreas;
    }

    /**
     * Returns an array of ids.
     * Note: Please search via string search to find usage of method.
     *
     * @return string[]
     */
    public function getPriorityAreaIds()
    {
        $result = [];
        foreach ($this->getPriorityAreas() as $pa) {
            $result[] = $pa->getId();
        }

        return $result;
    }

    /**
     * Returns an array of names.
     *
     * @return string[]
     */
    public function getPriorityAreaKeys()
    {
        $result = [];
        foreach ($this->getPriorityAreas() as $pa) {
            $result[] = $pa->getKey();
        }

        return $result;
    }

    /**
     * @param ArrayCollection|PriorityArea[] $priorityAreas
     *
     * @return $this
     */
    public function setPriorityAreas($priorityAreas)
    {
        $this->priorityAreas = new ArrayCollection($priorityAreas);

        return $this;
    }

    /**
     * Add Priority Area.
     */
    public function addPriorityArea(PriorityArea $priorityArea)
    {
        if (!$this->priorityAreas->contains($priorityArea)) {
            $this->priorityAreas->add($priorityArea);
            $priorityArea->addStatementFragment($this);
        }
    }

    /**
     * Remove Priority Area.
     */
    public function removePriorityArea(PriorityArea $priorityArea)
    {
        if ($this->priorityAreas->contains($priorityArea)) {
            $this->priorityAreas->removeElement($priorityArea);
            $priorityArea->removeStatementFragment($this);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getMunicipalities()
    {
        return $this->municipalities;
    }

    /**
     * Returns an array of ids.
     * Note: Please search via string search to find usage of method.
     *
     * @return string[]
     */
    public function getMunicipalityIds()
    {
        $result = [];
        foreach ($this->getMunicipalities() as $municipality) {
            $result[] = $municipality->getId();
        }

        return $result;
    }

    /**
     * Returns an array of names.
     *
     * @return string[]
     */
    public function getMunicipalityNames()
    {
        $result = [];
        foreach ($this->getMunicipalities() as $municipality) {
            $result[] = $municipality->getName();
        }

        return $result;
    }

    /**
     * @param ArrayCollection|Municipality[] $municipalities
     *
     * @return $this
     */
    public function setMunicipalities($municipalities)
    {
        $this->municipalities = new ArrayCollection($municipalities);

        return $this;
    }

    /**
     * Add Municipality.
     */
    public function addMunicipality(Municipality $municipality)
    {
        if (!$this->municipalities->contains($municipality)) {
            $this->municipalities->add($municipality);
            $municipality->addStatementFragment($this);
        }
    }

    /**
     * Remove Municipality.
     */
    public function removeMunicipality(Municipality $municipality)
    {
        if ($this->municipalities->contains($municipality)) {
            $this->municipalities->removeElement($municipality);
            $municipality->removeStatementFragment($this);
        }
    }

    /**
     * @return string|null
     */
    public function getArchivedOrgaName()
    {
        return $this->archivedOrgaName;
    }

    /**
     * @param string|null $archivedOrgaName
     */
    public function setArchivedOrgaName($archivedOrgaName)
    {
        $this->archivedOrgaName = $archivedOrgaName;
    }

    /**
     * @return Department
     */
    public function getArchivedDepartment()
    {
        return $this->archivedDepartment;
    }

    /**
     * @param Department|null $archivedDepartment
     */
    public function setArchivedDepartment($archivedDepartment)
    {
        $this->archivedDepartment = $archivedDepartment;
    }

    /**
     * Return DepartmentId for easier use in Elasticsearch.
     *
     * @return string|null
     */
    public function getArchivedDepartmentId()
    {
        if (!$this->archivedDepartment instanceof Department) {
            return null;
        }

        return $this->archivedDepartment->getId();
    }

    /**
     * @return string|null
     */
    public function getArchivedDepartmentName()
    {
        return $this->archivedDepartmentName;
    }

    /**
     * @param string|null $archivedDepartmentName
     */
    public function setArchivedDepartmentName($archivedDepartmentName)
    {
        $this->archivedDepartmentName = $archivedDepartmentName;
    }

    /**
     * @return string|null
     */
    public function getArchivedVoteUserName()
    {
        return $this->archivedVoteUserName;
    }

    /**
     * @param string|null $archivedVoteUserName
     */
    public function setArchivedVoteUserName($archivedVoteUserName)
    {
        $this->archivedVoteUserName = $archivedVoteUserName;
    }

    /**
     * @return User
     */
    public function getAssignee()
    {
        return $this->assignee;
    }

    /**
     * @param User|null $assignee
     */
    public function setAssignee($assignee)
    {
        $this->assignee = $assignee;
    }

    /**
     * @return Collection<int,StatementFragmentVersion>
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * @param Collection<StatementFragmentVersion>|array $versions
     */
    public function setVersions($versions)
    {
        $this->versions = $versions;
    }

    /**
     * Will add a Version at the beginning of the array to ensure order by created 'desc'.
     *
     * @param StatementFragmentVersion $version
     */
    public function addVersion($version)
    {
        $versionsArray = $this->versions->toArray();
        array_unshift($versionsArray, $version);
        $this->setVersions(new ArrayCollection($versionsArray));
    }

    /**
     * @return User
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
        if ($this->getModifiedByUser() instanceof User) {
            return $this->getModifiedByUser()->getId();
        }

        return $this->modifiedByUser;
    }

    /**
     * @param User $modifiedByUser
     */
    public function setModifiedByUser($modifiedByUser)
    {
        $this->modifiedByUser = $modifiedByUser;
    }

    /**
     * @return Department
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
        if ($this->getModifiedByDepartment() instanceof Department) {
            return $this->getModifiedByDepartment()->getId();
        }

        return null;
    }

    /**
     * @param Department $modifiedByDepartment
     */
    public function setModifiedByDepartment($modifiedByDepartment)
    {
        $this->modifiedByDepartment = $modifiedByDepartment;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/claim/ wiki: claiming
     *
     * @return User
     */
    public function getLastClaimed()
    {
        return $this->lastClaimed;
    }

    /**
     * @param User $user
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/claim/ wiki: claiming
     */
    public function setLastClaimed($user = null)
    {
        $this->lastClaimed = $user;
    }

    /**
     * Virtual property for easier Elasticsearch handling.
     *
     * @return string|null
     */
    public function getLastClaimedUserId()
    {
        if ($this->lastClaimed instanceof User) {
            return $this->lastClaimed->getId();
        }

        return null;
    }

    /**
     * Get elementId.
     *
     * @return string
     */
    public function getElementId()
    {
        if ($this->element instanceof Elements) {
            $this->elementId = $this->element->getId();
        }

        return $this->elementId;
    }

    /**
     * Get elementOrder.
     *
     * @return int
     */
    public function getElementOrder()
    {
        $elementOrder = 0;
        if ($this->element instanceof Elements) {
            $elementOrder = $this->element->getOrder();
        }

        return $elementOrder;
    }

    /**
     * Get elementTitle.
     *
     * @return string
     */
    public function getElementTitle()
    {
        if ($this->element instanceof Elements) {
            $this->elementTitle = $this->element->getTitle();
        }

        return $this->elementTitle;
    }

    /**
     * Get categoryType.
     *
     * Returns the category of the element that this statement refers to
     *
     * @return string
     */
    public function getElementCategory()
    {
        if ($this->element instanceof Elements) {
            return $this->element->getCategory();
        }

        return null;
    }

    /**
     * @return Elements|null
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param Elements|null $element
     */
    public function setElement($element)
    {
        $this->element = $element;

        // setze die ggf. zwischengespeicherten Daten zurück
        $this->elementId = null;
        $this->elementTitle = null;
    }

    /**
     * @return ParagraphVersion|null
     */
    public function getParagraph()
    {
        return $this->paragraph;
    }

    /**
     * @param ParagraphVersion|null $paragraph
     */
    public function setParagraph($paragraph)
    {
        $this->paragraph = $paragraph;
        // setze die ggf. zwischengespeicherten Daten zurück
        $this->paragraphId = null;
        $this->paragraphTitle = null;
        $this->paragraphOrder = 0;
    }

    /**
     * Get paragraphId.
     *
     * @return string
     */
    public function getParagraphId()
    {
        if ($this->paragraph instanceof ParagraphVersion) {
            $this->paragraphId = $this->paragraph->getId();
        }

        return $this->paragraphId;
    }

    /**
     * Get paragraphTitle.
     *
     * @return string
     */
    public function getParagraphTitle()
    {
        if ($this->paragraph instanceof ParagraphVersion) {
            $this->paragraphTitle = $this->paragraph->getTitle();
        }

        return trim($this->paragraphTitle);
    }

    /**
     * Get paragraphOrder.
     *
     * @return string
     */
    public function getParagraphOrder()
    {
        if ($this->paragraph instanceof ParagraphVersion) {
            $this->paragraphOrder = $this->paragraph->getOrder();
        }

        return $this->paragraphOrder;
    }

    /**
     * Get paragraphParentId.
     *
     * @return string
     */
    public function getParagraphParentId()
    {
        if (null === $this->paragraphParentId && $this->paragraph instanceof ParagraphVersion) {
            $parentId = null;
            if ($this->paragraph->getParagraph() instanceof Paragraph) {
                $parentId = $this->paragraph->getParagraph()->getId();
            }
            $this->paragraphParentId = $parentId;
        }

        return $this->paragraphParentId;
    }

    /**
     * @return string|null returns the title of the parent paragraph (the paragraph of the paragraph version)
     */
    public function getParagraphParentTitle()
    {
        if (null === $this->paragraphParentTitle && $this->paragraph instanceof ParagraphVersion) {
            $parentTitle = null;
            if ($this->paragraph->getParagraph() instanceof Paragraph) {
                $parentTitle = $this->paragraph->getParagraph()->getTitle();
            }
            $this->paragraphParentTitle = $parentTitle;
        }

        return trim($this->paragraphParentTitle);
    }

    /**
     * @return SingleDocumentVersion
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param SingleDocumentVersion $document
     */
    public function setDocument($document)
    {
        $this->document = $document;
    }

    /**
     * @return DateTime
     */
    public function getAssignedToFbDate()
    {
        return $this->assignedToFbDate;
    }

    /**
     * @param DateTime $assignedToFbDate
     */
    public function setAssignedToFbDate($assignedToFbDate)
    {
        $this->assignedToFbDate = $assignedToFbDate;
    }

    /**
     * Tells Elasticsearch whether Entity should be indexed.
     *
     * @return bool
     */
    public function shouldBeIndexed()
    {
        try {
            if ($this->getStatement()->isDeleted()) {
                return false;
            }
            if ($this->getProcedure()->isDeleted()) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Needed to create a grouped structure for an export in createElementsGroupStructure2().
     * This method allows to create a group structure with paragraphs and documents on the same level.
     */
    public function getParagraphParentIdOrDocumentParentId(): ?string
    {
        $id = $this->getParagraphParentId();
        if (null !== $id && '' !== $id) {
            return $id;
        }

        $id = $this->getDocumentParentId();
        if (null !== $id && '' !== $id) {
            return $id;
        }

        return null;
    }

    /**
     * Needed to create a grouped structure for an export in createElementsGroupStructure2().
     * This method allows to create a group structure with paragraphs and documents on the same level.
     */
    public function getParagraphParentTitleOrDocumentParentTitle(): ?string
    {
        $title = $this->getParagraphParentTitle();
        if (null !== $title && '' !== $title) {
            return $title;
        }

        $title = $this->getDocumentParentTitle();
        if (null !== $title && '' !== $title) {
            return $title;
        }

        return null;
    }

    /**
     * Get documentParentId.
     *
     * @return string
     */
    public function getDocumentParentId()
    {
        $documentId = null;
        if ($this->document instanceof SingleDocumentVersion) {
            $documentId = $this->document->getSingleDocument()->getId();
        }

        return $documentId;
    }

    // @improve T13720

    /**
     * @return string|null
     */
    public function getDocumentParentTitle()
    {
        $documentTitle = null;
        if ($this->document instanceof SingleDocumentVersion) {
            $documentTitle = $this->document->getSingleDocument()->getTitle();
        }

        return $documentTitle;
    }

    public function setCreated(DateTime $created)
    {
        $this->created = $created;
    }

    public function setModified(DateTime $modified)
    {
        $this->modified = $modified;
    }

    public function getSortIndex(): int
    {
        return $this->sortIndex;
    }

    public function setSortIndex(int $sortIndex): void
    {
        $this->sortIndex = $sortIndex;
    }
}
