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

use demosplan\DemosPlanCoreBundle\Entity\Category;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Category>
 *
 * @property-read End $name
 * @property-read End $deleted
 * @property-read End $enabled
 */
class GlobalNewsCategoryResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'GlobalNewsCategory';
    }

    public function getEntityClass(): string
    {
        return Category::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue(true, $this->enabled),
        ];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->name)->readable(),
        ];
    }
}
