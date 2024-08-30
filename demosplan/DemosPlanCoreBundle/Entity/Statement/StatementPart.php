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
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementPartRepository")
 *
 */
class StatementPart extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *                  Generates a UUID in code that confirms to https://www.w3.org/TR/1999/REC-xml-names-19990114/#NT-NCName
     *                  to be able to be used as xs:ID type in XML messages
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     */
    protected $id;

    /**
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="parts")
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", onDelete="CASCADE")
     */
    protected StatementInterface $statement;

    /**
     * Automatically generated ID shown to the planners and provided to the submitter (eg. when
     * submitting in the UI and as Email).
     *
     * @var string
     *
     * @ORM\Column(type="string", length=25, nullable=false, options={"fixed":true})
     */
    protected $externId = '';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false, options={"fixed":true})
     */
    protected $status = 'new';

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $created;
    /**
     * Type: TipTap-Editor String
     * Allowed values: May not be empty (https://demosdeutschland.slack.com/archives/C03AD7Z2Y/p1576674603017800).
     *
     * @var string
     *
     * @ORM\Column(type="text", nullable=false, length=15000000)
     */
    protected $text = '';

    /**
     * Sliced Version of StatementText for better Performance.
     *
     * @var string
     */
    protected $textShort = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false, length=15000000)
     */
    protected $recommendation = '';

    /**
     * Sliced Version of StatementRecommendation for better Performance.
     *
     * @var string
     */
    protected $recommendationShort = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    protected $memo = '';
    /**
     * @var string
     *
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    protected $reasonParagraph = '';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=4096, nullable=false)
     */
    protected $planningDocument = '';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"fixed":true}))
     */
    protected $file = '';

    /**
     * @var ParagraphVersion
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_pdv_id", onDelete="SET NULL")
     */
    protected $paragraph;

    /**
     * @var SingleDocumentVersion
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_sdv_id", onDelete="SET NULL")
     */
    protected $document;
    /**
     * @var Elements
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_e_id", onDelete="SET NULL")
     *
     **/
    protected $element;

    /**
     * @var string
     *
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    protected $polygon = '';


    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Tag", inversedBy="statements", cascade={"persist", "refresh"})
     *
     * @ORM\JoinTable(
     *     name="_statement_part_tag",
     *     joinColumns={@ORM\JoinColumn(name="id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_t_id", referencedColumnName="_t_id", onDelete="CASCADE")}
     * )
     */
    protected $tags;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     *
     * This is the user that is currently assigned to this statement. Assigned users are
     * exclusively permitted to change statements
     */
    protected $assignee;
    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $replied = false;
    /**
     * @var Collection<int,StatementAttachment>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\StatementAttachment", mappedBy="statement", cascade={"persist"})
     */
    protected $attachments;


    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id): StatementPart
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @deprecated use {@link Statement::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }

    public function setStatement(StatementInterface $statement): StatementPart
    {
        $this->statement = $statement;
        return $this;
    }

    public function getExternId(): string
    {
        return $this->externId;
    }

    public function setExternId(string $externId): StatementPart
    {
        $this->externId = $externId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): StatementPart
    {
        $this->status = $status;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): StatementPart
    {
        $this->text = $text;
        return $this;
    }

    public function getTextShort(): string
    {
        return $this->textShort;
    }

    public function setTextShort(string $textShort): StatementPart
    {
        $this->textShort = $textShort;
        return $this;
    }

    public function getRecommendation(): string
    {
        return $this->recommendation;
    }

    public function setRecommendation(string $recommendation): StatementPart
    {
        $this->recommendation = $recommendation;
        return $this;
    }

    public function getRecommendationShort(): string
    {
        return $this->recommendationShort;
    }

    public function setRecommendationShort(string $recommendationShort): StatementPart
    {
        $this->recommendationShort = $recommendationShort;
        return $this;
    }

    public function getMemo(): string
    {
        return $this->memo;
    }

    public function setMemo(string $memo): StatementPart
    {
        $this->memo = $memo;
        return $this;
    }

    public function getReasonParagraph(): string
    {
        return $this->reasonParagraph;
    }

    public function setReasonParagraph(string $reasonParagraph): StatementPart
    {
        $this->reasonParagraph = $reasonParagraph;
        return $this;
    }

    public function getPlanningDocument(): string
    {
        return $this->planningDocument;
    }

    public function setPlanningDocument(string $planningDocument): StatementPart
    {
        $this->planningDocument = $planningDocument;
        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): StatementPart
    {
        $this->file = $file;
        return $this;
    }

    public function getParagraph(): ParagraphVersion
    {
        return $this->paragraph;
    }

    public function setParagraph(ParagraphVersion $paragraph): StatementPart
    {
        $this->paragraph = $paragraph;
        return $this;
    }

    public function getDocument(): SingleDocumentVersion
    {
        return $this->document;
    }

    public function setDocument(SingleDocumentVersion $document): StatementPart
    {
        $this->document = $document;
        return $this;
    }

    public function getElement(): Elements
    {
        return $this->element;
    }

    public function setElement(Elements $element): StatementPart
    {
        $this->element = $element;
        return $this;
    }

    public function getPolygon(): string
    {
        return $this->polygon;
    }

    public function setPolygon(string $polygon): StatementPart
    {
        $this->polygon = $polygon;
        return $this;
    }

    public function getTags(): ArrayCollection|Collection
    {
        return $this->tags;
    }

    public function setTags(array $tags): StatementPart
    {
        $this->tags = new ArrayCollection($tags);
        return $this;
    }

    public function getAssignee(): User
    {
        return $this->assignee;
    }

    public function setAssignee(User $assignee): StatementPart
    {
        $this->assignee = $assignee;
        return $this;
    }

    public function isReplied(): bool
    {
        return $this->replied;
    }

    public function setReplied(bool $replied): StatementPart
    {
        $this->replied = $replied;
        return $this;
    }

    public function getAttachments(): ArrayCollection|Collection
    {
        return $this->attachments;
    }

    public function setAttachments(ArrayCollection|Collection $attachments): StatementPart
    {
        $this->attachments = $attachments;
        return $this;
    }

}
