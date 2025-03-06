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
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_elements")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ElementsRepository")
 */
class Elements extends CoreEntity implements UuidEntityInterface, ElementsInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_e_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_e_p_id", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $elementParentId;

    /**
     * @var ElementsInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", inversedBy="children")
     *
     * @ORM\JoinColumn(name="_e_p_id", referencedColumnName="_e_id", onDelete="SET NULL")
     */
    protected $parent;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $pId;

    /**
     * Das Procedure
     * T4999 cascade={"persist"} needed because of doctrine fuckup when deleting procedures (!).
     *
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="elements", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var string
     *
     * @ORM\Column(name="_e_category", type="string", length=255, options={"fixed":true}, nullable=false)
     */
    protected $category = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_e_title", type="string", length=256, nullable=false)
     */
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_e_icon", type="string", length=36, nullable=false)
     */
    protected $icon = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_e_icon_title", type="string", options={"comment":"Content of title-tag for icon"})
     */
    protected $iconTitle = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_e_text", type="text", length=65535, nullable=false)
     */
    protected $text = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_e_file", type="string", length=256, nullable=false, options={"default":""})
     */
    protected $file = '';

    /**
     * @var int
     *
     * @ORM\Column(name="_e_order", type="integer", nullable=false)
     */
    protected $order;

    /**
     * @var bool
     *
     * @ORM\Column(name="_e_enabled", type="boolean", nullable=false, options={"default":true})
     */
    protected $enabled = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_e_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_e_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_e_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_e_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var Collection<int,SingleDocument>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument", mappedBy="element")
     *
     * @ORM\OrderBy({"order" = "ASC", "createDate" = "ASC"})
     */
    protected $documents;

    /**
     * @var Collection<int,Elements>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", mappedBy="parent")
     *
     * @ORM\OrderBy({"order" = "ASC"})
     */
    protected $children;

    /**
     * @var Collection<int,Paragraph>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph", mappedBy="element")
     *
     * @ORM\OrderBy({"order" = "ASC"})
     */
    protected $paragraphs;

    /**
     * @var Collection<int,Orga>|Orga[]
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinTable(
     *     name="_elements_orga_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="_e_id", referencedColumnName="_e_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="CASCADE")}
     * )
     */
    protected $organisations;

    protected $type;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name = "_e_designated_switch_date", type = "datetime", nullable = true)
     */
    protected $designatedSwitchDate;

    /**
     * @var string|null
     *
     * @ORM\Column(type = "string", nullable = true, options={"default":null, "comment":"Needed permission, to get this element."})
     */
    protected $permission;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->organisations = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @deprecated use {@link Elements::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set parent ElementId.
     */
    public function setElementParentId(?string $parentId): self
    {
        $this->elementParentId = $parentId;

        return $this;
    }

    /**
     * Set parent element.
     */
    public function setParent(?ElementsInterface $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Get parent element.
     */
    public function getParent(): ?Elements
    {
        return $this->parent;
    }

    /**
     * Get the Id of the related ParentElement.
     */
    public function getElementParentId(): ?string
    {
        return $this->elementParentId;
    }

    /**
     * Set pId.
     */
    public function setPId(string $pId): self
    {
        $this->pId = $pId;

        return $this;
    }

    /**
     * Get pId.
     *
     * @return string
     *
     * @deprecated this methods name is misleading (parent vs procedure), use
     *             {@link Elements::getProcedure()} instead
     */
    public function getPId()
    {
        if (is_null($this->pId) && $this->procedure instanceof Procedure) {
            $this->pId = $this->procedure->getId();
        }

        return $this->pId;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    /**
     * @return $this
     */
    public function setProcedure(ProcedureInterface $procedure): self
    {
        $this->procedure = $procedure;
        $this->pId = $procedure->getId();

        return $this;
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
     * Set eIcon.
     */
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get eIcon.
     */
    public function getIcon(): string
    {
        return $this->icon;
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

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
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
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get eEnabled.
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
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
     * @return Collection<int, SingleDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    /**
     * @param ArrayCollection $documents
     */
    public function setDocuments(Collection $documents): void
    {
        $this->documents = $documents;
    }

    /**
     * @return Collection<int,Elements>|Elements[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @param ArrayCollection $children
     */
    public function setChildren(Collection $children): void
    {
        $this->children = $children;
    }

    /**
     * @return Collection<int,Orga>|Orga[]
     */
    public function getOrganisations(): Collection
    {
        return $this->organisations;
    }

    /**
     * @return array<>|string
     */
    public function getOrganisationNames($asString): array|string
    {
        $organisations = collect($this->getOrganisations())->map(
            function ($item) {
                return $item->getName();
            })->sort();

        if ($asString) {
            return $organisations->implode(', ');
        }

        return $organisations->toArray();
    }

    /**
     * @param Collection<int, Orga> $organisations
     */
    public function setOrganisations(Collection $organisations): void
    {
        $this->organisations = $organisations;
    }

    public function addOrganisation(OrgaInterface $organisation): void
    {
        if (!$this->organisations->contains($organisation)) {
            $this->organisations->add($organisation);
        }
    }

    public function removeOrganisation(OrgaInterface $organisation): void
    {
        if ($this->organisations->contains($organisation)) {
            $this->organisations->removeElement($organisation);
        }
    }

    public function getDesignatedSwitchDate(): ?DateTime
    {
        return $this->designatedSwitchDate;
    }

    public function setDesignatedSwitchDate(?DateTime $designatedSwitchDate): void
    {
        $this->designatedSwitchDate = $designatedSwitchDate;
    }

    public function getIconTitle(): string
    {
        return $this->iconTitle;
    }

    public function setIconTitle(string $iconTitle): void
    {
        $this->iconTitle = $iconTitle;
    }

    /**
     * This method will lead to an endless loop if an element can be have a child which have this element as a child.
     *
     * @return int - number of child elements including all children of children
     */
    public function countChildrenRecursively(): int
    {
        $children = $this->getChildren();
        $numberOfChildren = $children->count();

        /** @var Elements $child */
        foreach ($children as $child) {
            $numberOfChildren = $numberOfChildren + $child->countChildrenRecursively();
        }

        return $numberOfChildren;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function hasPermission(?string $permissionString): bool
    {
        return null === $this->permission || $permissionString === $this->getPermission();
    }

    /**
     * Will replace incoming empty string with null.
     */
    public function setPermission(string $permission): void
    {
        $this->permission = '' === $permission ? null : $permission;
    }

    public function getFileInfo(): FileInfo
    {
        $fileStringParts = explode(':', $this->getFile());

        return  new FileInfo(
            $fileStringParts[1],
            $fileStringParts[0],
            $fileStringParts[2],
            $fileStringParts[3],
            'missing',
            'missing',
            $this->procedure
        );
    }
}
