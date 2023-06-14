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
use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentVersionInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_single_doc_version")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SingleDocumentVersionRepository")
 */
class SingleDocumentVersion extends CoreEntity implements UuidEntityInterface, SingleDocumentVersionInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_sdv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Attention: This entity has to be persist, if the related singleDocument is deleted. Thats the reasons, why this relation is moddeled with nullable=true and onDelete=SET NULL.
     *
     * @var SingleDocumentInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument", inversedBy="versions")
     *
     * @ORM\JoinColumn(name="_sd_id", referencedColumnName="_sd_id", nullable=true, onDelete="SET NULL")
     */
    protected $singleDocument;

    /**
     * Virtuelle Eigenschaft der ParentSingledocumentId.
     *
     * @var string
     */
    protected $sdId;

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
     * @ORM\JoinColumn(name="_e_id", referencedColumnName="_e_id", nullable=true, onDelete="SET NULL")
     **/
    protected $element;

    /**
     * @var string
     *
     * @ORM\Column(name="_sd_category", type="string", length=36, options={"fixed":true}, nullable=false, length=36)
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
     * @ORM\Column(name="_sd_title", type="string", nullable=false, options={"default":""}, length=256)
     */
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
     * @var string
     *
     * @ORM\Column(name="_sd_document", type="string", nullable=false, length=256)
     */
    protected $document = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_sd_statement_enabled", type="boolean", nullable=false, options={"default":false})
     */
    protected $statementEnabled = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_sd_visible", type="boolean", nullable=false, options={"default":true})
     */
    protected $visible = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_sd_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_sdv_version_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $versionDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_sd_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_sd_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_sd_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set procedure.
     *
     * @param ProcedureInterface $procedure
     */
    public function setProcedure($procedure): self
    {
        $this->procedure = $procedure;
        if ($procedure instanceof ProcedureInterface) {
            $this->pId = $procedure->getId();
        }

        return $this;
    }

    /**
     * Get procedure.
     *
     * @return ProcedureInterface
     */
    public function getProcedure()
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

    /**
     * @return ElementsInterface|null
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param ElementsInterface $element
     */
    public function setElement($element)
    {
        if ($element instanceof ElementsInterface) {
            $this->elementId = $element->getId();
        }
        $this->element = $element;
    }

    /**
     * Set eCategory.
     *
     * @param string $category
     */
    public function setCategory($category): void
    {
        $this->category = $category;
    }

    /**
     * Get eCategory.
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set eTitle.
     *
     * @param string $title
     */
    public function setTitle($title): void
    {
        $this->title = $title;
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
     *
     * @param string $text
     */
    public function setText($text): void
    {
        $this->text = $text;
    }

    /**
     * Get eText.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @return string
     */
    public function getSymbol()
    {
        return $this->symbol;
    }

    /**
     * @param string $symbol
     */
    public function setSymbol($symbol)
    {
        $this->symbol = $symbol;
    }

    /**
     * @return string
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param string $document
     */
    public function setDocument($document)
    {
        $this->document = $document;
    }

    /**
     * Set eOrder.
     *
     * @param int $order
     */
    public function setOrder($order): void
    {
        $this->order = $order;
    }

    /**
     * Get eOrder.
     *
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return bool
     */
    public function isStatementEnabled()
    {
        return $this->statementEnabled;
    }

    /**
     * @param bool $statementEnabled
     */
    public function setStatementEnabled($statementEnabled)
    {
        $this->statementEnabled = $statementEnabled;
    }

    /**
     * Set eEnabled.
     */
    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    /**
     * Get eEnabled.
     *
     * @return bool
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Set eDeleted.
     *
     * @param bool $deleted
     */
    public function setDeleted($deleted): void
    {
        $this->deleted = $deleted;
    }

    /**
     * Get eDeleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set eCreateDate.
     *
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate): void
    {
        $this->createDate = $createDate;
    }

    /**
     * Get eCreateDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * Set eModifyDate.
     *
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate): void
    {
        $this->modifyDate = $modifyDate;
    }

    /**
     * Get eModifyDate.
     *
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * Set eDeleteDate.
     *
     * @param DateTime $deleteDate
     */
    public function setDeleteDate($deleteDate): void
    {
        $this->deleteDate = $deleteDate;
    }

    /**
     * Get eDeleteDate.
     *
     * @return DateTime
     */
    public function getDeleteDate()
    {
        return $this->deleteDate;
    }

    /**
     * @return SingleDocumentInterface|null
     */
    public function getSingleDocument()
    {
        return $this->singleDocument;
    }

    public function getSingleDocumentId()
    {
        if (null === $this->singleDocument) {
            return null;
        }

        return $this->singleDocument->getId();
    }

    /**
     * @param SingleDocumentInterface $singleDocument
     */
    public function setSingleDocument($singleDocument)
    {
        $this->singleDocument = $singleDocument;
    }
}
