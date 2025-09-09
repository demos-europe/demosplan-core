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

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<County>
 *
 * @property-read End $name
 */
final class CountyResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'County';
    }

    public function getEntityClass(): string
    {
        return County::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('field_statement_county');
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
        return [];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->filterable(),
            $this->createAttribute($this->name)->readable(true)->filterable()->sortable(),
        ];
    }
}
