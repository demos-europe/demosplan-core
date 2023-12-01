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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<HashedQuery>
 *
 * @property-read End $hash
 */
class HashedQueryResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'FilterSet';
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->hash)->readable(true),
        ];
    }

    public function getEntityClass(): string
    {
        return HashedQuery::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_assessmenttable');
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
}
