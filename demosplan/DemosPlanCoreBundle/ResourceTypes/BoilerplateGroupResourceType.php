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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<BoilerplateGroup>
 *
 * @property-read End                   $title
 * @property-read End                   $procedureId
 * @property-read ProcedureResourceType $procedure
 */
final class BoilerplateGroupResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'BoilerplateGroup';
    }

    public function getEntityClass(): string
    {
        return BoilerplateGroup::class;
    }

    public function isAvailable(): bool
    {
        return true;
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
            $this->createIdentifier()->readable()->filterable()->sortable(),
            $this->createAttribute($this->title)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->procedureId)->readable(true)->filterable()->sortable()
                ->aliasedPath($this->procedure->id),
        ];
    }
}
