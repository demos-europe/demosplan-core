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

use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<Slug>
 *
 * @property-read End $name
 */
final class SlugResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Slug';
    }

    public function getEntityClass(): string
    {
        return Slug::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->name)->readable(true),
        ];
    }
}
