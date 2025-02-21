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
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateGroupInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_predefined_texts")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository")
 */
class Boilerplate extends CoreEntity implements UuidEntityInterface, BoilerplateInterface
{
    /**
     * Unique identification of the boilerplate entry.
     *
     * @var string|null
     *
     * @ORM\Column(name="_pt_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * Not customisable. Cant be created, updated or delete by user.
     * Means in which area this Boilerplate will be used/loaded.
     *
     * @var Collection<int,BoilerplateCategoryInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory", mappedBy="boilerplates")
     *
     * @ORM\JoinTable(
     *     name="predefined_texts_categories",
     *     joinColumns={@ORM\JoinColumn(name="_pt_id", referencedColumnName="_pt_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_ptc_id", referencedColumnName="ptc_id", onDelete="CASCADE")}
     * )
     */
    protected $categories;

    /**
     * Customisable group. Can be created, updated, deleted by user.
     *
     * @var BoilerplateGroupInterface
     *
     * This Class/Entity is the owning side
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup", inversedBy="boilerplates")
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable = true)
     */
    protected $group;

    /**
     * @var Collection<int, TagInterface>
     *
     * @ORM\OneToMany(targetEntity = "\demosplan\DemosPlanCoreBundle\Entity\Statement\Tag", mappedBy = "boilerplate")
     */
    protected $tags;

    /**
     * @var string
     *
     * @ORM\Column(name="_pt_title", type="string", length=255, nullable=true)
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="_pt_text", type="text", nullable=true)
     */
    protected $text;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pt_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_pt_modify_date",type="datetime", nullable=false)
     */
    protected $modifyDate;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link Boilerplate::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * @param string $ident
     */
    public function setIdent($ident)
    {
        $this->ident = $ident;
    }

    public function getProcedureId(): string
    {
        return $this->getProcedure()->getId();
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

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
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
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @param bool $toString
     *
     * @return DateTime|string
     */
    public function getCreateDate($toString = true)
    {
        // ensure legacy format by default, because this might be the way it is still in use
        if ($toString) {
            $date = $this->createDate->format('Y-m-d H:i:s');
            $date[10] = 'T';

            return $date.'+0100';
        }

        return $this->createDate;
    }

    /**
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;
    }

    /**
     * @param bool $toString
     *
     * @return DateTime|string
     */
    public function getModifyDate($toString = true)
    {
        // ensure legacy format by default, because this might be the way it is still in use
        if ($toString) {
            $date = $this->modifyDate->format('Y-m-d H:i:s');
            $date[10] = 'T';

            return $date.'+0100';
        }

        return $this->modifyDate;
    }

    /**
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;
    }

    /**
     * @return ArrayCollection;
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Returns a specific tag of this boilerplate, if exists.
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
     * Add a specific BoilerplateCategory to this Boilerplate.
     *
     * @param BoilerplateCategoryInterface $boilerplateCategory
     *
     * @return BoilerplateInterface
     */
    public function addBoilerplateCategory($boilerplateCategory)
    {
        if (!$this->categories->contains($boilerplateCategory)) {
            $this->categories->add($boilerplateCategory);
            $boilerplateCategory->addBoilerplate($this);
        }

        return $this;
    }

    /**
     * Removes a specific BoilerplateCategory from this Boilerplate.
     *
     * @param BoilerplateCategoryInterface $boilerplateCategory
     *
     * @return BoilerplateInterface
     */
    public function removeBoilerplateCategory($boilerplateCategory)
    {
        $this->categories->removeElement($boilerplateCategory);

        // if boilerplateCategory->getTopic != null:
        $boilerplateCategory->removeBoilerplate(null);

        return $this;
    }

    /**
     * Returns this Boilerplate's categories.
     *
     * @return Collection<int,BoilerplateCategoryInterface>
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @return string[]
     */
    public function getCategoryTitles(): array
    {
        $categoryTitles = [];
        /** @var BoilerplateCategoryInterface $category */
        foreach ($this->categories as $category) {
            $categoryTitles[] = $category->getTitle();
        }

        return array_unique($categoryTitles);
    }

    /**
     * @param string $categoryTitle
     *
     * @return bool
     */
    public function hasCategory($categoryTitle)
    {
        return in_array($categoryTitle, $this->getCategoryTitles(), true);
    }

    /**
     * Sets this Boilerplate's categories.
     *
     * @param BoilerplateCategoryInterface[] $boilerplateCategories
     *
     * @return BoilerplateInterface
     */
    public function setCategories($boilerplateCategories)
    {
        /** @var BoilerplateCategoryInterface $category */
        foreach ($this->categories as $category) {
            $category->removeBoilerplate($this);
        }
        $this->categories = new ArrayCollection($boilerplateCategories);
        foreach ($boilerplateCategories as $category) {
            $category->addBoilerplate($this);
        }

        return $this;
    }

    /**
     * Add a specific Tag to this Boilerplate.
     *
     * @param TagInterface $tag
     *
     * @return BoilerplateInterface
     */
    public function addTag($tag)
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->setBoilerplate($this);
        }

        return $this;
    }

    /**
     * Removes a specific Tag from this Boilerplate.
     *
     * @param TagInterface $tag
     *
     * @return BoilerplateInterface
     */
    public function removeTag($tag)
    {
        $this->tags->removeElement($tag);

        // if tag->getTopic != null:
        if (!is_null($tag->getBoilerplate())) {
            $tag->setBoilerplate(null);
        }

        return $this;
    }

    /**
     * @return BoilerplateGroupInterface|null
     */
    public function getGroup()
    {
        return $this->group;
    }

    public function setGroup(BoilerplateGroupInterface $group)
    {
        if ($this->hasGroup() && $this->getGroupId() !== $group->getId()) {
            $this->group->removeBoilerplate($this);
        }

        $this->group = $group;
        $group->addBoilerplate($this);
    }

    public function detachGroup()
    {
        if ($this->hasGroup()) {
            $group = $this->group;
            $this->group = null;
            $group->removeBoilerplate($this);
        }
    }

    /**
     * @return string|null
     */
    public function getGroupId()
    {
        return $this->hasGroup() ? $this->getGroup()->getId() : null;
    }

    public function hasGroup(): bool
    {
        return null !== $this->getGroup();
    }
}
