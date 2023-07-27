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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<ProcedureUiDefinition>
 *
 * @template-extends DplanResourceType<ProcedureUiDefinition>
 *
 * @property-read End $statementFormHintStatement
 * @property-read End $statementFormHintPersonalData
 * @property-read End $statementFormHintRecheck
 * @property-read End $mapHintDefault
 * @property-read End $statementPublicSubmitConfirmationText
 * @property-read ProcedureResourceType $procedure
 * @property-read ProcedureTypeResourceType $procedureType
 */
final class ProcedureUiDefinitionResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public function getAccessCondition(): PathsBasedInterface
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            // if the user provided no procedure it must be a one with the permission to
            // access the list of ProcedureTypes and should be restricted to ProcedureUiDefinitions
            // that are connected to a ProcedureType (and thus not connected to a Procedure)
            return $this->conditionFactory->allConditionsApply(
                $this->conditionFactory->propertyIsNull($this->procedure),
                $this->conditionFactory->propertyIsNotNull($this->procedureType)
            );
        }

        return $this->conditionFactory->propertyHasValue(
            $currentProcedure->getId(),
            $this->procedure->id
        );
    }

    public function getEntityClass(): string
    {
        return ProcedureUiDefinition::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_procedure_type_edit');
    }

    public static function getName(): string
    {
        return 'ProcedureUiDefinition';
    }

    /**
     * @param ProcedureUiDefinition $object
     * @param array<string,mixed>   $properties
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        foreach ($properties as $propertyName => $value) {
            match ($propertyName) {
                $this->statementFormHintPersonalData->getAsNamesInDotNotation()         => $object->setStatementFormHintPersonalData($value),
                $this->statementFormHintRecheck->getAsNamesInDotNotation()              => $object->setStatementFormHintRecheck($value),
                $this->statementFormHintStatement->getAsNamesInDotNotation()            => $object->setStatementFormHintStatement($value),
                $this->mapHintDefault->getAsNamesInDotNotation()                        => $object->setMapHintDefault($value),
                $this->statementPublicSubmitConfirmationText->getAsNamesInDotNotation() => $object->setStatementPublicSubmitConfirmationText($value),
                default                                                                 => throw new InvalidArgumentException("Property not available for update: {$propertyName}"),
            };
        }

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->statementFormHintPersonalData,
            $this->statementFormHintRecheck,
            $this->statementFormHintStatement,
            $this->mapHintDefault,
            $this->statementPublicSubmitConfirmationText
        );
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
            $this->createAttribute($this->statementFormHintStatement)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->statementFormHintPersonalData)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->statementFormHintRecheck)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->mapHintDefault)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable(),
            $this->createAttribute($this->statementPublicSubmitConfirmationText)->readable(true)->sortable()->filterable(),
        ];
    }
}
