<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Document;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ParagraphInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ParagraphVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_para_doc")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository")
 */
class Paragraph extends CoreEntity implements UuidEntityInterface, ParagraphInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pd_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

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
     * @var ParagraphInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph", inversedBy="children")
     *
     * @ORM\JoinColumn(name="_pd_parent_id", referencedColumnName="_pd_id", onDelete="SET NULL")
     */
    protected $parent;

    /**
     * @var Collection<int, ParagraphInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph", mappedBy="parent")
     *
     * @ORM\OrderBy({"order" = "ASC"})
     */
    protected $children;

    /**
     * @var string
     */
    protected $elementId;

    /**
     * @var ElementsInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", inversedBy="paragraphs")
     *
     * @ORM\JoinColumn(name="_e_id", referencedColumnName="_e_id", nullable=false, onDelete="CASCADE")
     **/
    protected $element;

    /**
     * @var string
     *
     * @ORM\Column(name="_pd_category", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $category;

    /**
     * @var string
     *
     * @ORM\Column(name="_pd_title", type="text", length=65535, nullable=false)
     */
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pd_text", type="text", length=16777215, nullable=false)
     */
    protected $text = '';

    /**
     * @var int
     *
     * @ORM\Column(name="_pd_order", type="integer", nullable=false)
     */
    protected $order = 0;

    /**
     * @var int 1 = released, 2 = locked (Statement not possible but visible), 0 = blocked
     *
     * @ORM\Column(name="_pd_visible", type="integer", nullable=false)
     */
    protected $visible = 1;

    /**
     * @var bool
     *
     * @ORM\Column(name="_pd_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted;

    /**
     * @var string
     *
     * @ORM\Column(name="_pd_lockreason", type="string", nullable=false, length=300, options={"default":""})
     */
    protected $lockReason = '';

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pd_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_pd_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pd_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var ParagraphVersionInterface[]
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", mappedBy="paragraph")
     *
     * @ORM\JoinColumn(name="_pd_id", referencedColumnName="_pd_id")
     */
    protected $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getParent(): ?ParagraphInterface
    {
        return $this->parent;
    }

    /**
     * @return $this
     */
    public function setParent(?ParagraphInterface $parent): self
    {
        if ($parent instanceof ParagraphInterface) {
            $parent->addChild($this);
        }
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return ParagraphInterface[]
     */
    public function getChildren(): array
    {
        if (null !== $this->children) {
            return $this->children->getValues();
        } else {
            return [];
        }
    }

    /**
     * @param ParagraphInterface[] $children
     *
     * @return $this
     */
    public function setChildren(array $children): self
    {
        $this->children = new ArrayCollection($children);

        return $this;
    }

    /**
     * @return $this
     */
    public function addChild(ParagraphInterface $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function removeChild(ParagraphInterface $child): self
    {
        if ($this->children instanceof Collection && $this->children->contains($child)) {
            $this->children->removeElement($child);
        }

        return $this;
    }

    /**
     * Set procedure.
     */
    public function setProcedure(ProcedureInterface $procedure): self
    {
        $this->procedure = $procedure;
        $this->pId = $procedure->getId();

        return $this;
    }

    /**
     * Get procedure.
     */
    public function getProcedure(): ProcedureInterface
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
        if (is_null($this->pId) && $this->procedure instanceof ProcedureInterface) {
            $this->pId = $this->procedure->getId();
        }

        return $this->pId;
    }

    /**
     * Get elementId.
     *
     * @return string
     */
    public function getElementId()
    {
        if (is_null($this->elementId) && $this->element instanceof ElementsInterface) {
            $this->elementId = $this->element->getId();
        }

        return $this->elementId;
    }

    public function getElement(): ElementsInterface
    {
        return $this->element;
    }

    public function setElement(ElementsInterface $element): void
    {
        $this->element = $element;
    }

    /**
     * Set eCategory.
     */
    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get eCategory.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Set eTitle.
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get eTitle.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set eText.
     */
    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get eText.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Set eOrder.
     */
    public function setOrder(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get eOrder.
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Set eEnabled.
     */
    public function setVisible(int $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Get eEnabled.
     */
    public function getVisible(): int
    {
        return $this->visible;
    }

    /**
     * Set eDeleted.
     */
    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get eDeleted.
     */
    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Set lockReason.
     */
    public function setLockReason(string $lockReason): self
    {
        $this->lockReason = $lockReason;

        return $this;
    }

    /**
     * Get lockReason.
     */
    public function getLockReason(): string
    {
        return $this->lockReason;
    }

    /**
     * Set eCreateDate.
     */
    public function setCreateDate(DateTime $createDate): self
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get eCreateDate.
     */
    public function getCreateDate(): DateTime
    {
        return $this->createDate;
    }

    /**
     * Set eModifyDate.
     */
    public function setModifyDate(DateTime $modifyDate): self
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * Get eModifyDate.
     */
    public function getModifyDate(): DateTime
    {
        return $this->modifyDate;
    }

    /**
     * Set eDeleteDate.
     */
    public function setDeleteDate(DateTime $deleteDate): self
    {
        $this->deleteDate = $deleteDate;

        return $this;
    }

    /**
     * Get eDeleteDate.
     */
    public function getDeleteDate(): DateTime
    {
        return $this->deleteDate;
    }

    /**
     * @return Collection<int, ParagraphVersionInterface>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }
}
