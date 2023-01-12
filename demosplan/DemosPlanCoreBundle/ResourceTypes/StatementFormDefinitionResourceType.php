<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<StatementFormDefinition>
 *
 * @property-read StatementFieldDefinitionResourceType $fieldDefinitions
 * @property-read ProcedureResourceType $procedure
 * @property-read ProcedureTypeResourceType $procedureType
 */
final class StatementFormDefinitionResourceType extends DplanResourceType
{
    public function getAccessCondition(): PathsBasedInterface
    {
        $procedureTypeEditAllowed = $this->currentUser->hasPermission('area_procedure_type_edit');

        // If edit permission for ProcedureType (independent of procedures),
        // then allow access to those StatementFormDefinition
        // that are not connected to a specific procedure.
        if ($procedureTypeEditAllowed) {
            return $this->conditionFactory->allConditionsApply(
                $this->conditionFactory->propertyIsNotNull($this->procedureType),
                $this->conditionFactory->propertyIsNull($this->procedure)
            );
        }

        // If no edit permission for ProcedureType and not authenticated for any procedure, deny any reads.
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return $this->conditionFactory->false();
        }

        // If no edit permission for ProcedureType, allow access to StatementFormDefinitions
        // connected to the given Procedure.
        return $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue($currentProcedure->getId(), $this->procedure->id),
            $this->conditionFactory->propertyIsNull($this->procedureType)
        );
    }

    public function getEntityClass(): string
    {
        return StatementFormDefinition::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_procedure_type_edit');
    }

    public static function getName(): string
    {
        return 'StatementFormDefinition';
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->fieldDefinitions)->readable()->sortable()->filterable(),
            $this->createToManyRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable(),
        ];
    }
}
