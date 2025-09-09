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

use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
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
final class ProcedureUiDefinitionResourceType extends DplanResourceType
{
    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            // if the user provided no procedure it must be a one with the permission to
            // access the list of ProcedureTypes and should be restricted to ProcedureUiDefinitions
            // that are connected to a ProcedureType (and thus not connected to a Procedure)
            return [
                $this->conditionFactory->propertyIsNull($this->procedure),
                $this->conditionFactory->propertyIsNotNull($this->procedureType),
            ];
        }

        return [$this->conditionFactory->propertyHasValue(
            $currentProcedure->getId(),
            $this->procedure->id
        )];
    }

    public function getEntityClass(): string
    {
        return ProcedureUiDefinition::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_procedure_type_edit');
    }

    public function isUpdateAllowed(): bool
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

    public function getUpdatableProperties(): array
    {
        return [
            $this->statementFormHintPersonalData->getAsNamesInDotNotation()         => null,
            $this->statementFormHintRecheck->getAsNamesInDotNotation()              => null,
            $this->statementFormHintStatement->getAsNamesInDotNotation()            => null,
            $this->mapHintDefault->getAsNamesInDotNotation()                        => null,
            $this->statementPublicSubmitConfirmationText->getAsNamesInDotNotation() => null,
        ];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->statementFormHintStatement)->readable(true)->sortable()->filterable()->updatable(),
            $this->createAttribute($this->statementFormHintPersonalData)->readable(true)->sortable()->filterable()->updatable(),
            $this->createAttribute($this->statementFormHintRecheck)->readable(true)->sortable()->filterable()->updatable(),
            $this->createAttribute($this->mapHintDefault)->readable(true)->sortable()->filterable()->updatable(),
            $this->createToOneRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable(),
            $this->createAttribute($this->statementPublicSubmitConfirmationText)->readable(true)->sortable()->filterable()->updatable(),
        ];
    }
}
