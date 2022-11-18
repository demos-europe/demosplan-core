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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<ProcedureBehaviorDefinition>
 *
 * @template-extends DplanResourceType<ProcedureBehaviorDefinition>
 *
 * @property-read End $allowedToEnableMap
 * @property-read End $hasPriorityArea
 * @property-read End $participationGuestOnly
 * @property-read ProcedureResourceType $procedure
 * @property-read ProcedureTypeResourceType $procedureType
 */
final class ProcedureBehaviorDefinitionResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public function getAccessCondition(): PathsBasedInterface
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            // if the user provided no procedure it must be a one with the permission to
            // access the list of ProcedureTypes and should be restricted to ProcedureBehaviorDefinitions
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
        return ProcedureBehaviorDefinition::class;
    }

    public function isAvailable(): bool
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
            switch ($propertyName) {
                case $this->allowedToEnableMap->getAsNamesInDotNotation():
                    $object->setAllowedToEnableMap($value);
                    break;
                case $this->hasPriorityArea->getAsNamesInDotNotation():
                    $object->setHasPriorityArea($value);
                    break;
                case $this->participationGuestOnly->getAsNamesInDotNotation():
                    $object->setParticipationGuestOnly($value);
                    break;
                default:
                    throw new InvalidArgumentException("Property not available for update: {$propertyName}");
            }
        }

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->allowedToEnableMap,
            $this->hasPriorityArea,
            $this->participationGuestOnly
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
            $this->createAttribute($this->allowedToEnableMap)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->hasPriorityArea)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->participationGuestOnly)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable(),
        ];
    }
}
