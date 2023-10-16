<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Map;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ContextualHelpInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class GisLayer.
 *
 * @ORM\Table(name="_gis", indexes={@ORM\Index(name="_g_global_id", columns={"_g_global_id"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\MapRepository")
 */
class GisLayer extends CoreEntity implements GisLayerInterface
{
    /**
     * Unique identification of the Gislayer entry.
     *
     * @var string|null
     *
     * @ORM\Column(name="_g_id", type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var bool
     *
     * @ORM\Column(name="_g_bplan", type="boolean", nullable=false, options={"default":false})
     */
    protected $bplan = false;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $defaultVisibility = false;
    /**
     * @var bool
     *
     * @ORM\Column(name="_g_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_g_legend", type="string", length=512, nullable=false, options={"default":""})
     */
    protected $legend = '';

    /**
     * @var string
     *
     *@ORM\Column(name="_g_layers", type="string", length=4096, nullable=false)
     */
    protected $layers = '';

    /**
     * LayerName.
     *
     * @var string
     *
     * @ORM\Column(name="_g_name", type="string", length=256, nullable=false)
     */
    protected $name = '';

    /**
     * @var int
     *
     * @ORM\Column(name="_g_opacity", type="integer", length=3, nullable=false, options={"default":100})
     */
    protected $opacity = 100;

    /**
     * @var int
     *
     * @ORM\Column(name="_g_order", type="integer", length=4, nullable=false, options={"default":0})
     */
    protected $order = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="_projection_label", type="string", nullable=false,
     *              options={"default":"EPSG:3857"})
     */
    protected $projectionLabel = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_projection_value", type="string", nullable=false,
     *              options={"default":"+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs"})
     */
    protected $projectionValue = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_p_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $procedureId = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_g_print", type="boolean", nullable=false, options={"default":false})
     */
    protected $print = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_g_scope", type="boolean", nullable=false, options={"default":false})
     */
    protected $scope = false;

    /**
     * todo: potential improvement: this is not needed?!
     *
     * @var bool
     *
     * @ORM\Column(name="_g_scope1", type="boolean", nullable=false, options={"default":false})
     */
    protected $scope1 = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_g_type", type="string", length=64, nullable=false)
     */
    protected $type = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_g_servicetype", type="string", length=12, nullable=false, options={"default":"wms"})
     */
    protected $serviceType = 'wms';

    /**
     * @var string
     *
     * @ORM\Column(name="_g_cabilities", type="text", length=65535, nullable=true)
     */
    protected $capabilities;

    /**
     * @var string
     *
     * @ORM\Column(name="_g_tile_matrix_set", type="string", length=256, nullable=true)
     */
    protected $tileMatrixSet;

    /**
     * @var string
     *
     * @ORM\Column(name="_g_url", type="string", length=4096, nullable=false)
     */
    protected $url = '';

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    protected $enabled = true;

    // improve T16806
    /**
     * @var bool
     */
    protected $globalLayer = false;

    /**
     * GlobalLayerId.
     *
     * @var string
     *
     * @ORM\Column(name="_g_global_id", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $gId;

    /**
     * GlobalLayer.
     *
     * @var array
     */
    protected $globalGis;

    /**
     * The service version for the layer.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $layerVersion = '1.3.0';

    /**
     * @var bool
     *
     * @ORM\Column(name="_g_xplan", type="boolean", nullable=false, options={"default":false})
     */
    protected $xplan = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_g_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_g_modify_date",type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_g_delete_date",type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var ContextualHelpInterface
     *
     * @ORM\OneToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp", cascade={"remove"}, fetch="EAGER")
     *
     * @ORM\JoinColumn(name="_g_pcsh_id", referencedColumnName="_pcsh_id", onDelete="SET NULL")
     */
    protected $contextualHelp;

    /**
     * @var GisLayerCategoryInterface
     *
     * Many GisLayers has one GisLayerCategory
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory", inversedBy="gisLayers", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id")
     */
    protected $category;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"default":0})
     */
    protected $treeOrder = 0;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    protected $userToggleVisibility = true;

    /**
     * VisibilityGroupId.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=true, options={"default":null})
     */
    protected $visibilityGroupId;

    /**
     * Is this GisLayer the one shown in the minimap?
     *
     * @var bool
     *
     * @ORM\Column(name="_g_is_minimap", type="boolean", nullable=false, options={"default":false})
     */
    protected $isMiniMap = false;

    /**
     * Set data from DSL.
     *
     * @return GisLayer $this
     */
    public function set($data)
    {
        if (isset($data['globalGis'])) {
            $this->setGlobalLayer(true);
            // Überschreibe die Werte des Layers mit den globalen Daten
            foreach ($this->getGlobalLayerFields() as $field) {
                $data[$field] = $data['globalGis'][$field];
            }
        } else {
            $this->setGlobalLayer(false);
        }
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    protected function getGlobalLayerFields()
    {
        return [
            'name',
            'url',
            'layers',
            'type',
            'serviceType',
            'tileMatrixSet',
            'legend',
        ];
    }

    /**
     * Wandle das Objekt in ein Array um.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'bplan'             => $this->bplan,
            'default'           => $this->defaultVisibility,
            'defaultVisibility' => $this->defaultVisibility,
            'deleted'           => $this->deleted,
            'ident'             => $this->ident,
            'isMinimap'         => $this->isMiniMap(),
            'legend'            => $this->legend,
            'layers'            => $this->layers,
            'name'              => $this->name,
            'opacity'           => $this->opacity,
            'procedureId'       => $this->procedureId,
            'print'             => $this->print,
            'scope'             => $this->scope,
            'type'              => $this->type,
            'url'               => $this->url,
            'visible'           => $this->enabled,
            'enabled'           => $this->enabled,
            'globalLayer'       => $this->globalLayer,
            'globalLayerId'     => $this->gId,
            'xplan'             => $this->xplan,
        ];
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link GisLayer::getId()} instead
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

    /**
     * @return bool
     */
    public function isBplan()
    {
        return (bool) $this->bplan;
    }

    /**
     * @param bool $bplan
     */
    public function setBplan($bplan)
    {
        $this->bplan = $bplan;
    }

    /**
     * @return bool
     */
    public function hasDefaultVisibility()
    {
        return (bool) $this->defaultVisibility;
    }

    /**
     * @param bool $defaultVisibility
     *
     * @return GisLayer
     */
    public function setDefaultVisibility($defaultVisibility)
    {
        $this->defaultVisibility = $defaultVisibility;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * @param bool $deleted
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    /**
     * @return string
     */
    public function getLegend()
    {
        return $this->legend;
    }

    /**
     * @param string $legend
     *
     * @return GisLayer
     */
    public function setLegend($legend)
    {
        $this->legend = $legend;

        return $this;
    }

    /**
     * @return string
     */
    public function getLayers()
    {
        return $this->layers;
    }

    /**
     * @param string $layers
     *
     * @return GisLayer
     */
    public function setLayers($layers)
    {
        $this->layers = $layers;

        return $this;
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
     *
     * @return GisLayer
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return int
     */
    public function getOpacity()
    {
        return $this->opacity;
    }

    /**
     * @param int $opacity
     *
     * @return GisLayer
     */
    public function setOpacity($opacity)
    {
        $this->opacity = $opacity;

        return $this;
    }

    /**
     * @return string
     */
    public function getProcedureId()
    {
        return $this->procedureId;
    }

    /**
     * Alias, damit Twig auf pId zugreifen kann.
     *
     * @return string
     */
    public function getPId()
    {
        return $this->getProcedureId();
    }

    /**
     * @param string $procedureId
     *
     * @return GisLayer
     */
    public function setProcedureId($procedureId)
    {
        $this->procedureId = $procedureId;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPrint()
    {
        return (bool) $this->print;
    }

    /**
     * @param bool $print
     */
    public function setPrint($print)
    {
        $this->print = $print;
    }

    /**
     * @return bool
     */
    public function isScope()
    {
        return (bool) $this->scope;
    }

    /**
     * @param bool $scope
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return GisLayer
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getServiceType()
    {
        if ('' === $this->serviceType) {
            $this->serviceType = 'wms';
        }

        return $this->serviceType;
    }

    /**
     * @param string $serviceType
     */
    public function setServiceType($serviceType)
    {
        $this->serviceType = $serviceType;
    }

    /**
     * @return string
     */
    public function getCapabilities()
    {
        return $this->capabilities;
    }

    /**
     * @param string $capabilities
     */
    public function setCapabilities($capabilities)
    {
        $this->capabilities = $capabilities;
    }

    /**
     * @return string
     */
    public function getTileMatrixSet()
    {
        return $this->tileMatrixSet;
    }

    /**
     * @param string $tileMatrixSet
     */
    public function setTileMatrixSet($tileMatrixSet)
    {
        $this->tileMatrixSet = $tileMatrixSet;
    }

    /**
     * @param bool $editMode Editiermodus, wird in HH benötigt
     *
     * @return string
     */
    public function getUrl($editMode = false)
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return GisLayer
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool) $enabled;
    }

    // improve T16806

    /**
     * @return bool
     */
    public function isGlobalLayer()
    {
        return $this->globalLayer;
    }

    // improve T16806

    /**
     * @param bool $globalLayer
     */
    public function setGlobalLayer($globalLayer)
    {
        $this->globalLayer = $globalLayer;
    }

    /**
     * @return string
     */
    public function getGlobalLayerId()
    {
        return $this->gId;
    }

    /**
     * @param string $globalLayerId
     */
    public function setGlobalLayerId($globalLayerId)
    {
        $this->gId = $globalLayerId;
    }

    /**
     * @return string
     */
    public function getGId()
    {
        return $this->gId;
    }

    /**
     * @param string $gId
     */
    public function setGId($gId)
    {
        $this->gId = $gId;
    }

    /**
     * @return bool
     */
    public function isXplan()
    {
        return (bool) $this->xplan;
    }

    /**
     * @param bool $xplan
     *
     * @return GisLayer
     */
    public function setXplan($xplan)
    {
        $this->xplan = $xplan;

        return $this;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param int $order
     *
     * @return GisLayer
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * @param DateTime $createDate
     *
     * @return GisLayer
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * @param DateTime $modifyDate
     *
     * @return GisLayer
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDeleteDate()
    {
        return $this->deleteDate;
    }

    /**
     * @param DateTime $deleteDate
     *
     * @return GisLayer
     */
    public function setDeleteDate($deleteDate)
    {
        $this->deleteDate = $deleteDate;

        return $this;
    }

    /**
     * @return array
     */
    public function getGlobalGis()
    {
        return $this->globalGis;
    }

    /**
     * @param array $globalGis
     */
    public function setGlobalGis($globalGis)
    {
        $this->globalGis = $globalGis;
    }

    public function getLayerVersion(): string
    {
        return $this->layerVersion;
    }

    public function setLayerVersion(string $layerVersion): void
    {
        $this->layerVersion = $layerVersion;
    }

    /**
     * @param ContextualHelpInterface $help
     *
     * @return GisLayer
     */
    public function setContextualHelp($help)
    {
        $this->contextualHelp = $help;

        return $this;
    }

    /**
     * @return ContextualHelp
     */
    public function getContextualHelp()
    {
        return $this->contextualHelp;
    }

    /**
     * @return GisLayerCategory|null
     */
    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory(GisLayerCategoryInterface $category)
    {
        $category->getGisLayers()->add($this);
        $this->category = $category;
    }

    /**
     * @return string
     */
    public function getCategoryId()
    {
        return $this->getCategory() instanceof GisLayerCategory ? $this->getCategory()->getId() : '';
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
     * @return bool
     */
    public function canUserToggleVisibility()
    {
        return $this->userToggleVisibility;
    }

    /**
     * @return bool
     */
    public function getUserToggleVisibility()
    {
        return $this->userToggleVisibility;
    }

    /**
     * @param bool $userToggleVisibility
     */
    public function setUserToggleVisibility($userToggleVisibility)
    {
        $this->userToggleVisibility = $userToggleVisibility;
    }

    /**
     * @return string
     */
    public function getVisibilityGroupId()
    {
        return $this->visibilityGroupId;
    }

    /**
     * @param string|null $visibilityGroupId
     */
    public function setVisibilityGroupId($visibilityGroupId)
    {
        $this->visibilityGroupId = ('' === $visibilityGroupId) ? null : $visibilityGroupId;
    }

    /**
     * @return bool
     */
    public function isBaseLayer()
    {
        return 'base' === $this->getType();
    }

    /**
     * @return bool
     */
    public function isMiniMap()
    {
        return $this->isMiniMap;
    }

    /**
     * @param bool $isMiniMap
     */
    public function setIsMiniMap($isMiniMap)
    {
        $this->isMiniMap = $isMiniMap;
    }

    public function isOverlay(): bool
    {
        return GisLayerInterface::TYPE_OVERLAY === $this->getType();
    }

    public function getProjectionLabel(): string
    {
        return $this->projectionLabel;
    }

    public function setProjectionLabel(string $projectionLabel): void
    {
        $this->projectionLabel = $projectionLabel;
    }

    public function getProjectionValue(): string
    {
        return $this->projectionValue;
    }

    public function setProjectionValue(string $projectionValue): void
    {
        $this->projectionValue = $projectionValue;
    }
}
