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

use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<ContextualHelp>
 *
 * @property-read End $ident
 * @property-read End $key
 * @property-read End $text
 */
final class ContextualHelpResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'ContextualHelp';
    }

    public function getEntityClass(): string
    {
        return ContextualHelp::class;
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

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable()->aliasedPath($this->ident),
            $this->createAttribute($this->key)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->text)->readable(true)->sortable()->filterable(),
        ];
    }
}
