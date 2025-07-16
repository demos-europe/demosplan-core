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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * SingleDocument is one of two possible childs of Elements.
 * Elements can have SingleDocument if it (Elements) has the category (which is more like a type) 'file'.
 * SingleDocument holds files.
 *
 * The other possibility is an Elements with category paragraph. Those Elements are linked to
 * paragraphs. Those can't hold files.
 *
 * @ORM\Table(name="_single_doc")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository")
 */
class SingleDocument extends CoreEntity implements SingleDocumentInterface, UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_sd_id", type="string", length=36, options={"fixed":true})
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
     * @var string
     */
    protected $elementId;

    /**
     * @var ElementsInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", inversedBy="documents")
     *
     * @ORM\JoinColumn(name="_e_id", referencedColumnName="_e_id", nullable=false, onDelete="CASCADE")
     **/
    protected $element;

    /**
     * @var string
     *
     * @ORM\Column(name="_sd_category", type="string", nullable=false, length=36)
     */
    protected $category;

    /**
     * @var int
     *
     * @ORM\Column(name="_sd_order", type="integer", nullable=false, length=10)
     */
    protected $order = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="_sd_title", type="string", length=256, nullable=false)
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, message: 'error.mandatoryfield.heading', groups: [SingleDocument::IMPORT_CREATION])]
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_sd_text", type="text", nullable=false, length=65535)
     */
    protected $text = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_sd_symbol", type="string", nullable=false, length=36)
     */
    protected $symbol = '';

    /**
     * Filestring of a File.
     *
     * @var string
     *
     * @ORM\Column(name="_sd_document", type="string", nullable=false, length=256)
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, message: 'error.mandatoryfield.file', groups: [SingleDocument::IMPORT_CREATION])]
    protected $document = '';

    /**
     * todo: potential improvement default:true.
     *
     * @var bool
     *
     * @ORM\Column(name="_sd_statement_enabled", type="boolean", nullable=false, options={"default":false})
     */
    protected $statementEnabled = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_sd_visible", type="boolean", nullable=false, options={"default":true}))
     */
    protected $visible = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_sd_deleted", type="boolean", nullable=false, options={"default":false}))
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_sd_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_sd_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_sd_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var SingleDocumentVersionInterface[]
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion", mappedBy="singleDocument")
     *
     * @ORM\JoinColumn(name="_sd_id", referencedColumnName="_sd_id")
     */
    protected $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
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
        $this->elementId = $element->getId();
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

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getDocument(): string
    {
        return $this->document;
    }

    public function setDocument(string $document): void
    {
        $this->document = $document;
    }

    /**
     * @return Collection<int, SingleDocumentVersionInterface>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    /**
     * Set Order.
     */
    public function setOrder(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get Order.
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    public function isStatementEnabled(): bool
    {
        return $this->statementEnabled;
    }

    public function setStatementEnabled(bool $statementEnabled): void
    {
        $this->statementEnabled = $statementEnabled;
    }

    /**
     * Set eEnabled.
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Get eEnabled.
     */
    public function getVisible(): bool
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
     * Set eCreateDate.
     */
    public function setCreateDate(DateTime $createDate): self
    {
        $this->createDate = $createDate;

        return $this;
    }

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
     *  If there is no file info in the SingleDocument object, returns an associative array keeping its keys
     *  ['name','hash', 'size', 'mimeType'] but with empty values.
     *
     * @return array<string, string>
     */
    public function getSingleDocumentInfo(): array
    {
        $fileInfo = ['name' => '', 'hash' => '', 'size' => '', 'mimeType' => ''];

        $documentStringParts = explode(':', $this->getDocument());
        if (count($documentStringParts) >= 4) {
            $fileInfo['name'] = $documentStringParts[0];
            $fileInfo['hash'] = $documentStringParts[1];
            $fileInfo['size'] = $documentStringParts[2];
            $fileInfo['mimeType'] = $documentStringParts[3];
        }

        return $fileInfo;
    }

    public function getFileInfo(): FileInfo
    {
        $fileStringParts = explode(':', $this->getDocument());

        return new FileInfo(
            $fileStringParts[1] ?? '',
            $fileStringParts[0] ?? '',
            (int)($fileStringParts[2] ?? ''),
            $fileStringParts[3] ?? '',
            'missing',
            'missing',
            $this->procedure
        );
    }
}
