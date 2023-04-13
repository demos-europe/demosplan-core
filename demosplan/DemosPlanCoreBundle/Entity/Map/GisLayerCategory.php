<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Map;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerCategoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class GisLayerCategory.
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\GisLayerCategoryRepository")
 */
class GisLayerCategory extends CoreEntity implements GisLayerCategoryInterface
{
    /**
     * Unique identification of the GisLayerCategory entry.
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $name;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var Collection<int, GisLayer>
     *                                One GisLayerCategory has many GisLayers
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer", mappedBy="category", fetch="EAGER")
     */
    protected $gisLayers;

    /**
     * @var GisLayerCategory
     *
     * Parent GisLayerCategory
     *
     * If this is null, we have arrived at the root category of a procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory", inversedBy="children", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var Collection<int, GisLayerCategory>
     *
     * Child categories of a categorys
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory", mappedBy="parent", cascade={"persist"})
     */
    protected $children;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"default":0})
     */
    protected $treeOrder = 0;

    // @improve T16792
    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    protected $visible = true;

    /**
     * Hides all children for the category and displays the category as layer instead.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false, "comment":"Hides all children for the category and displays the category as layer instead."})
     */
    protected $layerWithChildrenHidden = false;

    public function __construct()
    {
        $this->gisLayers = new ArrayCollection();
        $this->children = new ArrayCollection();
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

    /**
     * @return Procedure
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param Procedure $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * @return ArrayCollection
     */
    public function getGisLayers()
    {
        return $this->gisLayers;
    }

    /**
     * @param GisLayer[] $gisLayers
     */
    public function setGisLayers(array $gisLayers)
    {
        foreach ($gisLayers as $gisLayer) {
            $gisLayer->setCategory($this);
        }

        $this->gisLayers = new ArrayCollection($gisLayers);
    }

    public function addLayer(GisLayer $gisLayer): void
    {
        if (null === $this->gisLayers) {
            $this->gisLayers = new ArrayCollection();
        }

        $gisLayer->setCategory($this);

        if (!$this->gisLayers->contains($gisLayer)) {
            $this->gisLayers->add($gisLayer);
        }
    }

    /**
     * @return int
     */
    public function getTreeOrder()
    {
        return $this->treeOrder;
    }

    /**
     * @param int $treeOrder
     */
    public function setTreeOrder($treeOrder)
    {
        $this->treeOrder = $treeOrder;
    }

    /**
     * @return GisLayerCategory
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return string
     */
    public function getParentId()
    {
        return is_null($this->getParent()) ? null : $this->getParent()->getId();
    }

    /**
     * @param GisLayerCategory $newParent
     */
    public function setParent($newParent)
    {
        if (is_null($newParent)) {
            throw new InvalidArgumentException('Set null as Parent is invalid, use rootCategory instead.');
        }

        // detach from current parent if set:
        if (false === is_null($this->getParent())) {
            $this->getParent()->getChildren()->removeElement($this);
        }

        // attach to new parent:
        $newParent->getChildren()->add($this);

        // set new parent:
        $this->parent = $newParent;
    }

    /**
     * @return ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param GisLayerCategory[] $children
     */
    public function setChildren($children)
    {
        if (is_array($children) && 0 === count($children)) {
            throw new InvalidArgumentException('Cannot removing children from Category by set empty array.
                 Set rootCategory as parent of children to remove instead.');
        }

        foreach ($children as $child) {
            $child->setParent($this);
        }
        $this->children = $children;
    }

    // @improve T16792

    /**
     * @return bool
     */
    public function isVisible()
    {
        return $this->visible;
    }

    // @improve T16792

    /**
     * @param bool $visible
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return is_null($this->getParent());
    }

    /**
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;
    }

    /**
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;
    }

    /**
     * @return bool
     */
    public function isLayerWithChildrenHidden()
    {
        return $this->layerWithChildrenHidden;
    }

    /**
     * @param bool $layerWithChildrenHidden
     */
    public function setLayerWithChildrenHidden($layerWithChildrenHidden)
    {
        $this->layerWithChildrenHidden = $layerWithChildrenHidden;
    }
}
