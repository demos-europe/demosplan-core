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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ProcedureBehaviorDefinition>
 *
 * @property-read End $allowedToEnableMap
 * @property-read End $hasPriorityArea
 * @property-read End $participationGuestOnly
 * @property-read ProcedureResourceType $procedure
 * @property-read ProcedureTypeResourceType $procedureType
 */
final class ProcedureBehaviorDefinitionResourceType extends DplanResourceType
{
    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            // if the user provided no procedure it must be a one with the permission to
            // access the list of ProcedureTypes and should be restricted to ProcedureBehaviorDefinitions
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
        return ProcedureBehaviorDefinition::class;
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
        return 'ProcedureBehaviorDefinition';
    }

    /**
     * @param ProcedureBehaviorDefinition $object
     * @param array<string,mixed>         $properties
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        foreach ($properties as $propertyName => $value) {
            match ($propertyName) {
                $this->allowedToEnableMap->getAsNamesInDotNotation()     => $object->setAllowedToEnableMap($value),
                $this->hasPriorityArea->getAsNamesInDotNotation()        => $object->setHasPriorityArea($value),
                $this->participationGuestOnly->getAsNamesInDotNotation() => $object->setParticipationGuestOnly($value),
                default                                                  => throw new InvalidArgumentException("Property not available for update: {$propertyName}"),
            };
        }

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(): array
    {
        return [
            $this->allowedToEnableMap->getAsNamesInDotNotation()     => null,
            $this->hasPriorityArea->getAsNamesInDotNotation()        => null,
            $this->participationGuestOnly->getAsNamesInDotNotation() => null,
        ];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->allowedToEnableMap)->readable(true)->sortable()->filterable()->updatable(),
            $this->createAttribute($this->hasPriorityArea)->readable(true)->sortable()->filterable()->updatable(),
            $this->createAttribute($this->participationGuestOnly)->readable(true)->sortable()->filterable()->updatable(),
            $this->createToOneRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable(),
        ];
    }
}
