<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<GisLayer>
 *
 * @property-read End $ident
 * @property-read End $isGlobalLayer
 * @property-read End $globalLayer
 * @property-read End $globalLayerId
 * @property-read End $gId
 * @property-read End $hasDefaultVisibility
 * @property-read End $defaultVisibility
 * @property-read End $isBplan
 * @property-read End $bplan
 * @property-read End $isPrint
 * @property-read End $print
 * @property-read End $isEnabled
 * @property-read End $enabled
 * @property-read End $isXplan
 * @property-read End $xplan
 * @property-read End $legend
 * @property-read End $layers
 * @property-read End $layerVersion
 * @property-read End $name
 * @property-read End $mapOrder
 * @property-read End $order
 * @property-read End $opacity
 * @property-read End $procedureId
 * @property-read End $serviceType
 * @property-read End $type
 * @property-read End $isBaseLayer
 * @property-read End $tileMatrixSet
 * @property-read End $url
 * @property-read End $treeOrder
 * @property-read End $categoryId
 * @property-read End $canUserToggleVisibility
 * @property-read End $userToggleVisibility
 * @property-read End $visibilityGroupId
 * @property-read End $isScope
 * @property-read End $scope
 * @property-read End $isMinimap
 * @property-read End $isMiniMap
 * @property-read End $projectionValue
 * @property-read End $projectionLabel
 * @property-read End $createdAt
 * @property-read ContextualHelpResourceType $contextualHelp
 */
final class GisLayerResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'GisLayer';
    }

    public function getEntityClass(): string
    {
        return GisLayer::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createAttribute($this->legend)->readable(true),
            $this->createAttribute($this->layers)->readable(true),
            $this->createAttribute($this->layerVersion)->readable(true),
            $this->createAttribute($this->name)->readable(true),
            $this->createAttribute($this->opacity)->readable(true),
            $this->createAttribute($this->procedureId)->readable(true),
            $this->createAttribute($this->type)->readable(true),
            $this->createAttribute($this->tileMatrixSet)->readable(true),
            $this->createAttribute($this->treeOrder)->readable(true),
            $this->createAttribute($this->projectionValue)->readable(true),
            $this->createAttribute($this->projectionLabel)->readable(true),
            $this->createAttribute($this->id)
                ->readable(true)->aliasedPath($this->ident),
            $this->createAttribute($this->isGlobalLayer)
                ->readable(true)->aliasedPath($this->globalLayer),
            $this->createAttribute($this->globalLayerId)
                ->readable(true)->aliasedPath($this->gId),
            $this->createAttribute($this->hasDefaultVisibility)
                ->readable(true)->aliasedPath($this->defaultVisibility),
            $this->createAttribute($this->isBplan)
                ->readable(true)->aliasedPath($this->bplan),
            $this->createAttribute($this->isPrint)
                ->readable(true)->aliasedPath($this->print),
            $this->createAttribute($this->isEnabled)
                ->readable(true)->aliasedPath($this->enabled),
            $this->createAttribute($this->isXplan)
                ->readable(true)->aliasedPath($this->xplan),
            $this->createAttribute($this->mapOrder)
                ->readable(true)->aliasedPath($this->order),
            $this->createAttribute($this->canUserToggleVisibility)
                ->readable(true)->aliasedPath($this->userToggleVisibility),
            $this->createAttribute($this->isScope)
                ->readable(true)->aliasedPath($this->scope),
            $this->createAttribute($this->isMinimap)
                ->readable(true)->aliasedPath($this->isMiniMap),
            // Keep this as a default include because these relationships are included in
            // GisLayerCategories and available filters are not usable for nested resources yet.
            $this->createToOneRelationship($this->contextualHelp, true)
                ->readable(true),
            $this->createAttribute($this->serviceType)
                ->readable(true, static fn(GisLayer $gisLayer): string => $gisLayer->getServiceType()),
            $this->createAttribute($this->isBaseLayer)
                ->readable(true, static fn(GisLayer $gisLayer): bool => $gisLayer->isBaseLayer()),
            $this->createAttribute($this->url)
                ->readable(true, static fn(GisLayer $gisLayer): string => $gisLayer->getUrl()),
            $this->createAttribute($this->categoryId)
                ->readable(true, static fn(GisLayer $gisLayer): string => $gisLayer->getCategoryId()),
            $this->createAttribute($this->visibilityGroupId)
                ->readable(true, static fn(GisLayer $gisLayer): string => $gisLayer->getVisibilityGroupId() ?? ''),
        ];

        if ($this->currentUser->hasPermission('area_admin_map')) {
            $properties[] = $this->createAttribute($this->createdAt)
                ->readable(true, fn(GisLayer $gisLayer): string => $this->formatDate($gisLayer->getCreateDate()));
        }

        return $properties;
    }
}
