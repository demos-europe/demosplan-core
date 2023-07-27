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

use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

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

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasPermission('area_map_participation_area');
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->name)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->layerWithChildrenHidden)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->treeOrder)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->isVisible)
                ->readable(true)->sortable()->filterable()->aliasedPath($this->visible),
            $this->createAttribute($this->hasDefaultVisibility)
                ->readable(true)->sortable()->filterable()->aliasedPath($this->visible),
            $this->createAttribute($this->parentId)
                ->readable(true)->sortable()->filterable()->aliasedPath($this->parent->id),

            /*
             * Keep these as a default include because these relationships are recursive and currently not easily
             * manageable in the FE with the actual - correct - available includes syntax.
             */
            $this->createToManyRelationship($this->categories, true)
                ->readable(true)->sortable()->filterable()->aliasedPath($this->children),
            $this->createToManyRelationship($this->gisLayers, true)->readable(true)->sortable()->filterable(),
        ];
    }
}
