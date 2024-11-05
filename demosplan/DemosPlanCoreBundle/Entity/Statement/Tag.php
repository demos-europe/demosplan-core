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
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     name="_tag",
 *     uniqueConstraints={
 *
 *         @ORM\UniqueConstraint(
 *             name="tag_unique_title",
 *             columns={"_tt_id", "_t_title"}
 *         )
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\TagRepository")
 */
class Tag extends CoreEntity implements UuidEntityInterface, TagInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_t_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var TagTopicInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic", inversedBy="tags", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_tt_id", referencedColumnName="_tt_id", nullable = false)
     */
    #[Assert\NotNull(groups: [ResourceTypeService::VALIDATION_GROUP_DEFAULT, 'segments_import'])]
    #[Assert\Type(groups: ['segments_import'], type: 'demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic')]
    protected $topic;

    /**
     * @var string
     *
     * @ORM\Column(name="_t_title", type="string", length=255, nullable=false)
     */
    #[Assert\NotBlank(groups: [ResourceTypeService::VALIDATION_GROUP_DEFAULT, 'segments_import'], message: 'Tag title may not be empty.')]
    protected $title = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_t_create_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createDate;

    /**
     * @var Collection<int,StatementInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="tags", cascade={"persist", "refresh"})
     *
     * @ORM\JoinTable(
     *     name="_statement_tag",
     *     joinColumns={@ORM\JoinColumn(name="_t_id", referencedColumnName="_t_id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id")}
     * )
     */
    protected $statements;

    /**
     * @var BoilerplateInterface
     *
     * @ORM\JoinColumn(name="_pt_id", referencedColumnName="_pt_id", onDelete="SET NULL")
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate", inversedBy="tags")
     */
    protected $boilerplate;

    /**
     * @var bool
     *
     * @ORM\Column(name="topicalTag", type="boolean", nullable=false, options={"default":false})
     */
    protected $topicalTag = false;

    /**
     * Create a Tag-Entity.
     *
     * @param string $title
     */
    public function __construct($title, TagTopic $topic)
    {
        $this->setTitle($title);
        $this->topic = $topic;
        $this->setTopic($topic);
        $this->statements = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getTopic(): TagTopicInterface
    {
        return $this->topic;
    }

    /**
     * @return string
     */
    public function getTopicTitle()
    {
        if ($this->topic instanceof TagTopicInterface) {
            return $this->topic->getTitle();
        }

        return '';
    }

    /**
     * Assign this Tag to a specific TagTopic.
     * Because a Tag can have one Topic only, it is necessary to remove this Tag from the current Topic (if exists).
     * Add this Tag to the given Topic and save the information of relation in this object.
     *
     * @param TagTopicInterface $newTopic
     *
     * @return TagInterface $this
     */
    public function setTopic($newTopic)
    {
        if ($newTopic instanceof TagTopicInterface) {
            $this->getTopic()->removeTag($this);
            $newTopic->addTag($this);
            $this->topic = $newTopic;
        }

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
     * @return string
     *
     * @deprecated use {@link getTitle} instead
     */
    public function getName()
    {
        return $this->getTitle();
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createDate;
    }

    /**
     * @return ProcedureInterface
     *
     * @deprecated Only needed for Elasticsearch indexing. Use {@link TagTopicInterface::getProcedure()} instead.
     */
    public function getProcedure()
    {
        return $this->topic->getProcedure();
    }

    /**
     * Add Statement.
     *
     * @param StatementInterface $statement
     *
     * @return bool - true if the given statement was added to this tag, otherwise false
     */
    public function addStatement($statement)
    {
        $successful = false;
        if (!$this->statements->contains($statement)) {
            $successful = $this->statements->add($statement);
        }

        return $successful;
    }

    /**
     * Sets the boilerplate text that is associated to this tag.
     *
     * @param BoilerplateInterface|null $boilerplate
     */
    public function setBoilerplate($boilerplate)
    {
        $this->boilerplate = $boilerplate;
    }

    /**
     * Returns the boilerplate text that is associated with this tag.
     *
     * @return BoilerplateInterface|null
     */
    public function getBoilerplate()
    {
        return $this->boilerplate;
    }

    /**
     * Determines if this Tag has a boilerplate.
     *
     * @return bool - true there are a boilerplate, otherwise false
     */
    public function hasBoilerplate()
    {
        return false === is_null($this->boilerplate);
    }

    /**
     * @return ArrayCollection
     */
    public function getStatements()
    {
        return $this->statements;
    }

    /**
     * @param ArrayCollection $statements
     */
    public function setStatements($statements)
    {
        $this->statements = $statements;
    }

    /**
     * @param DateTime $date
     */
    public function setCreateDate($date)
    {
        $this->createDate = $date;
    }

    public function isTopicalTag(): bool
    {
        return $this->topicalTag;
    }

    public function setTopicalTag(bool $topicalTag): void
    {
        $this->topicalTag = $topicalTag;
    }
}
