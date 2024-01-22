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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<PriorityArea>
 *
 * @property-read End $name
 * @property-read End $priorityAreaType
 * @property-read End $key
 */
final class PriorityAreaResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'PriorityArea';
    }

    public function getEntityClass(): string
    {
        return PriorityArea::class;
    }

    public function isAvailable(): bool
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return false;
        }

        $behaviorDefinition = $procedure->getProcedureBehaviorDefinition();
        if (null === $behaviorDefinition) {
            return false;
        }

        if (!$behaviorDefinition->hasPriorityArea()) {
            return false;
        }
        if (!$this->currentUser->hasPermission('area_admin_assessmenttable')) {
            $formDefinition = $procedure->getStatementFormDefinition();
            if (null === $formDefinition) {
                return false;
            }

            if (!$formDefinition->isFieldDefinitionEnabled(StatementFormDefinition::MAP_AND_COUNTY_REFERENCE)) {
                return false;
            }
        }

        return $this->currentUser->hasPermission('field_statement_priority_area');
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
            $this->createAttribute($this->name)->readable(true)->filterable()->sortable()->aliasedPath($this->key),
            $this->createAttribute($this->priorityAreaType)
                ->readable(true)->filterable()->sortable()->aliasedPath(Paths::priorityArea()->type),
            $this->createAttribute($this->key)->readable(true)->filterable()->sortable(),
        ];
    }
}
