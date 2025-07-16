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
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<GisLayerCategory>
 *
 * @property-read End $name
 * @property-read End $layerWithChildrenHidden
 * @property-read End $treeOrder
 * @property-read End $isVisible @deprecated use {@link GisLayerCategoryResourceType::$visible} instead
 * @property-read End $visible
 * @property-read End $hasDefaultVisibility
 * @property-read End $parentId @deprecated use {@link GisLayerCategoryResourceType::$parent} instead
 * @property-read GisLayerCategoryResourceType $parent
 * @property-read GisLayerCategoryResourceType $parentCategory
 * @property-read GisLayerCategoryResourceType $categories
 * @property-read GisLayerCategoryResourceType $children
 * @property-read GisLayerResourceType $gisLayers
 */
final class GisLayerCategoryResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'GisLayerCategory';
    }

    public function getEntityClass(): string
    {
        return GisLayerCategory::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    public function isListAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    public function isDeleteAllowed(): bool
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
            $this->conditionFactory->propertyHasValue($procedureId, Paths::gisLayerCategory()->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::gisLayerCategory()->procedure->deleted),
        ];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->name)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->layerWithChildrenHidden)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->treeOrder)
                ->updatable()
                ->readable(true)
                ->sortable()
                ->filterable(),
            $this->createAttribute($this->isVisible)
                ->readable(true)->sortable()->filterable()->aliasedPath($this->visible),
            $this->createAttribute($this->hasDefaultVisibility)
                ->updatable()
                ->readable(true)
                ->sortable()
                ->filterable()
                ->aliasedPath($this->visible),
            $this->createAttribute($this->parentId)
                ->updatable()
                ->readable(true)->sortable()->filterable()->aliasedPath($this->parent->id),

            $this->createToOneRelationship($this->parentCategory)
                ->updatable()
                ->readable(true)
                ->sortable()
                ->filterable()
                ->aliasedPath($this->parent),

            /*
             * Keep these as a default include because these relationships are recursive and currently not easily
             * manageable in the FE with the actual - correct - available includes syntax.
             */
            $this->createToManyRelationship($this->categories)
                ->readable(true, null, true)
                ->sortable()
                ->filterable()
                ->aliasedPath($this->children),
            $this->createToManyRelationship($this->gisLayers)
                ->readable(true, null, true)
                ->sortable()
                ->filterable(),
        ];
    }
}
