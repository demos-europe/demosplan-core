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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(
 *     name="_tag_topic",
 *     uniqueConstraints={
 *
 *         @ORM\UniqueConstraint(
 *             name="tag_topic_unique_title",
 *             columns={"_p_id", "_tt_title"}
 *         )
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository")
 */
class TagTopic extends CoreEntity implements UuidEntityInterface, TagTopicInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_tt_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @ORM\Column(name="_tt_title", type="string", length=255,  nullable=false)
     */
    protected string $title = '';

    /**
     * @ORM\Column(name="_tt_create_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected DateTime $createDate;

    /**
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="topics")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable = false, onDelete="CASCADE")
     */
    protected ProcedureInterface $procedure;

    /**
     * @var Collection<int, TagInterface>
     *
     * @ORM\OneToMany(targetEntity = "\demosplan\DemosPlanCoreBundle\Entity\Statement\Tag", mappedBy = "topic", cascade={"remove"})
     *
     * @ORM\OrderBy({"title" = "ASC"})
     */
    protected Collection $tags;

    /**
     * * Necessary to set Type of $this->tags.
     */
    public function __construct(string $title = '', ProcedureInterface $procedure)
    {
        $this->tags = new ArrayCollection();
        $this->title = $title;
        $this->procedure = $procedure;
    }

    /**
     * @return Collection<int, TagInterface>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * Returns a specific tag of this topic, if exists.
     *
     * @param string $id identifies the tag
     *
     * @return TagInterface|null
     */
    public function getTag($id)
    {
        $allTags = $this->getTags()->getValues();

        foreach ($allTags as $tag) {
            if ($tag->getId() == $id) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Add a specific Tag to this Topic.
     *
     * @param Tag $tag
     *
     * @return TagTopicInterface
     */
    public function addTag($tag)
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    /**
     * Removes a specific Tag from this Topic.
     *
     * @param TagInterface $tag
     *
     * @return TagTopicInterface
     */
    public function removeTag($tag)
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @param string|null $id
     */
    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createDate;
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
     * Set title.
     *
     * @param string $title
     *
     * @return string
     * @return TagTopicInterface
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return ProcedureInterface
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param ProcedureInterface $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }
}
