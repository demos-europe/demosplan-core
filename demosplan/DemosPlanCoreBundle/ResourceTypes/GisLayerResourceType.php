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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

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
 * @property-read End $layerType
 * @property-read End $isBaseLayer
 * @property-read End $tileMatrixSet
 * @property-read End $url
 * @property-read End $treeOrder
 * @property-read End $categoryId
 * @property-read GisLayerCategoryResourceType $category
 * @property-read GisLayerCategoryResourceType $parentCategory
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

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isDeleteAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    public function isGetAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    public function isListAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    public function isUpdateAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    protected function hasManagementPermission(): bool
    {
        return $this->currentUser->hasPermission('area_map_participation_area');
    }

    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $currentProcedure->getId();

        return [
            $this->conditionFactory->propertyHasValue($procedureId, Paths::gisLayer()->category->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::gisLayer()->category->procedure->deleted),
        ];
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
            $this->createAttribute($this->layerType)->readable(true)->aliasedPath(Paths::gisLayer()->type),
            $this->createAttribute($this->tileMatrixSet)->readable(true),
            $this->createAttribute($this->treeOrder)->updatable()->readable(true),
            $this->createAttribute($this->projectionValue)->readable(true),
            $this->createAttribute($this->projectionLabel)->readable(true),
            $this->createIdentifier()
                ->readable()->aliasedPath($this->ident),
            $this->createAttribute($this->isGlobalLayer)
                ->readable(true)->aliasedPath($this->globalLayer),
            $this->createAttribute($this->globalLayerId)
                ->readable(true)->aliasedPath($this->gId),
            $this->createAttribute($this->hasDefaultVisibility)
                ->updatable()
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
                ->updatable()
                ->readable(true)->aliasedPath($this->order),
            $this->createAttribute($this->canUserToggleVisibility)
                ->readable(true)->aliasedPath($this->userToggleVisibility),
            $this->createAttribute($this->isScope)
                ->readable(true)->aliasedPath($this->scope),
            $this->createAttribute($this->isMinimap)->updatable()
                ->readable(true)->aliasedPath($this->isMiniMap),
            // Keep this as a default include because these relationships are included in
            // GisLayerCategories and available filters are not usable for nested resources yet.
            $this->createToOneRelationship($this->contextualHelp)
                ->readable(true, null, true),
            $this->createAttribute($this->serviceType)
                ->readable(true, static fn (GisLayer $gisLayer): string => $gisLayer->getServiceType()),
            $this->createAttribute($this->isBaseLayer)
                ->readable(true, static fn (GisLayer $gisLayer): bool => $gisLayer->isBaseLayer()),
            $this->createAttribute($this->url)
                ->readable(true, static fn (GisLayer $gisLayer): string => $gisLayer->getUrl()),
            $this->createAttribute($this->categoryId)
                ->readable(true, static fn (GisLayer $gisLayer): string => $gisLayer->getCategoryId()),
            $this->createToOneRelationship($this->parentCategory)
                ->updatable()
                ->readable(true)
                ->sortable()
                ->filterable()
                ->aliasedPath($this->category),
            $this->createAttribute($this->visibilityGroupId)
                ->updatable()
                ->readable(true, static fn (GisLayer $gisLayer): string => $gisLayer->getVisibilityGroupId() ?? ''),
        ];

        if ($this->currentUser->hasPermission('area_admin_map')) {
            $properties[] = $this->createAttribute($this->createdAt)
                ->readable(true, fn (GisLayer $gisLayer): string => $this->formatDate($gisLayer->getCreateDate()));
        }

        return $properties;
    }
}
