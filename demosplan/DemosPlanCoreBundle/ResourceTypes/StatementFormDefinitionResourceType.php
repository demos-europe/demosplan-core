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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * @template-extends DplanResourceType<StatementFormDefinition>
 *
 * @property-read StatementFieldDefinitionResourceType $fieldDefinitions
 * @property-read ProcedureResourceType $procedure
 * @property-read ProcedureTypeResourceType $procedureType
 */
final class StatementFormDefinitionResourceType extends DplanResourceType
{
    protected function getAccessConditions(): array
    {
        $procedureTypeEditAllowed = $this->currentUser->hasPermission('area_procedure_type_edit');

        // If edit permission for ProcedureType (independent of procedures),
        // then allow access to those StatementFormDefinition
        // that are not connected to a specific procedure.
        if ($procedureTypeEditAllowed) {
            return [
                $this->conditionFactory->propertyIsNotNull($this->procedureType),
                $this->conditionFactory->propertyIsNull($this->procedure),
            ];
        }

        // If no edit permission for ProcedureType and not authenticated for any procedure, deny any reads.
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        // If no edit permission for ProcedureType, allow access to StatementFormDefinitions
        // connected to the given Procedure.
        return [
            $this->conditionFactory->propertyHasValue($currentProcedure->getId(), $this->procedure->id),
            $this->conditionFactory->propertyIsNull($this->procedureType),
        ];
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

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createToManyRelationship($this->fieldDefinitions)->readable()->sortable()->filterable(),
            $this->createToManyRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable(),
        ];
    }
}
