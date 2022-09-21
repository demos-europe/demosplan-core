<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(
 *     name="_tag_topic",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(
 *             name="tag_topic_unique_title",
 *             columns={"_p_id", "_tt_title"}
 *         )
 *     }
 * )
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanStatementBundle\Repository\TagTopicRepository")
 */
class TagTopic extends CoreEntity implements UuidEntityInterface
{
    public const TAG_TOPIC_MISC = 'Sonstiges';

    /**
     * @var string|null
     *
     * @ORM\Column(name="_tt_id", type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="_tt_title", type="string", length=255,  nullable=false)
     */
    protected $title = '';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="_tt_create_date", type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="create")
     */
    protected $createDate;

    /**
     * @var \demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="topics")
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable = false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var Collection<int, Tag>
     *
     * @ORM\OneToMany(targetEntity = "\demosplan\DemosPlanCoreBundle\Entity\Statement\Tag", mappedBy = "topic", cascade={"remove"})
     * @ORM\OrderBy({"title" = "ASC"})
     */
    protected $tags;

    /**
     * * Necessary to set Type of $this->tags.
     *
     * @param string    $title
     * @param Procedure $procedure
     */
    public function __construct($title, $procedure)
    {
        $this->tags = new ArrayCollection();
        $this->setTitle($title);
        $this->setProcedure($procedure);
    }

    /**
     * @return Collection<int, Tag>
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
     * @return Tag|null
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
     * @return TagTopic
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
     * @param Tag $tag
     *
     * @return TagTopic
     */
    public function removeTag($tag)
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     *
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
     * @return \DateTime
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
     * @return TagTopic
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return \demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param \demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }
}
