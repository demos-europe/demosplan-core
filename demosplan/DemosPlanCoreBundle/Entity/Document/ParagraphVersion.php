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
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ParagraphVersionInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_para_doc_version")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ParagraphVersionRepository")
 */
class ParagraphVersion extends CoreEntity implements UuidEntityInterface, ParagraphVersionInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pdv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Attention: This entity has to be persist, if the related paragraph is deleted. Thats the reasons, why this relation is moddeled with nullable=true and onDelete=SET NULL.
     *
     * @var Paragraph
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph", inversedBy="versions")
     *
     * @ORM\JoinColumn(name="_pd_id", referencedColumnName="_pd_id", nullable=true, onDelete="SET NULL")
     */
    protected $paragraph;

    /**
     * Virtuelle Eigenschaft der ParentparagraphId.
     *
     * @var string
     */
    protected $pdId;

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
     */
    protected $pId;

    /**
     * @var string
     */
    protected $elementId;

    /**
     * Attention: This entity has to be persist, if the related paragraph is deleted. Thats the reasons, why this relation is moddeled with nullable=true and onDelete=SET NULL.
     *
     * @var Elements
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements")
     *
     * @ORM\JoinColumn(name="_e_id", referencedColumnName="_e_id", nullable=true, onDelete="SET NULL")
     **/
    protected $element;

    /**
     * todo: potential improvement options={"fixed":true}.
     *
     * @var string
     *
     * @ORM\Column(name="_pd_category", type="string", length=36, nullable=false)
     */
    protected $category;

    /**
     * todo: potential improvement type="string".
     *
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
     * @var bool
     *
     * @ORM\Column(name="_pd_visible", type="boolean", nullable=false)
     */
    protected $visible;

    /**
     * @var bool
     *
     * @ORM\Column(name="_pd_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pdv_version_date", type="datetime", nullable=false)
     */
    protected $versionDate;

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

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Get elementId.
     *
     * @return string
     */
    public function getElementId()
    {
        if (is_null($this->elementId) && $this->element instanceof Elements) {
            $this->elementId = $this->element->getId();
        }

        return $this->elementId;
    }

    /**
     * @return Elements|null
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param Elements $element
     */
    public function setElement($element)
    {
        $this->element = $element;
    }

    /**
     * @return Paragraph
     */
    public function getParagraph()
    {
        return $this->paragraph;
    }

    /**
     * @param Paragraph $paragraph
     */
    public function setParagraph($paragraph)
    {
        $this->paragraph = $paragraph;
    }

    /**
     * Set procedure.
     *
     * @param Procedure $procedure
     *
     * @return ParagraphVersion
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
        if ($procedure instanceof Procedure) {
            $this->pId = $procedure->getId();
        }

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
     * Get procedureId.
     *
     * @return string
     */
    public function getPId()
    {
        if (is_null($this->pId) && $this->procedure instanceof Procedure) {
            $this->pId = $this->procedure->getId();
        }

        return $this->pId;
    }

    /**
     * Set eCategory.
     *
     * @param string $category
     *
     * @return ParagraphVersion
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
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
     *
     * @return ParagraphVersion
     */
    public function setTitle($title)
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
     *
     * @param string $text
     *
     * @return ParagraphVersion
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
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
     * Set eOrder.
     *
     * @param int $order
     *
     * @return ParagraphVersion
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
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
     * Set eEnabled.
     *
     * @param bool $visible
     *
     * @return ParagraphVersion
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;

        return $this;
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
     *
     * @return ParagraphVersion
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
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
     *
     * @return ParagraphVersion
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
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
     *
     * @return ParagraphVersion
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;

        return $this;
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
     *
     * @return ParagraphVersion
     */
    public function setDeleteDate($deleteDate)
    {
        $this->deleteDate = $deleteDate;

        return $this;
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
}
