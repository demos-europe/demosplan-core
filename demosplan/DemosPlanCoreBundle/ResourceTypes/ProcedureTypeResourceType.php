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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<ProcedureType>
 *
 * @template-extends DplanResourceType<ProcedureType>
 *
 * @property-read End $name
 * @property-read End $description
 * @property-read StatementFormDefinitionResourceType $statementFormDefinition
 * @property-read ProcedureUiDefinitionResourceType $procedureUiDefinition
 * @property-read ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinition
 */
final class ProcedureTypeResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    protected function getAccessConditions(): array
    {
        return [];
    }

    public function getEntityClass(): string
    {
        return ProcedureType::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_admin_procedures',
            'area_procedure_type_edit'
        );
    }

    public static function getName(): string
    {
        return 'ProcedureType';
    }

    /**
     * @param ProcedureType       $object
     * @param array<string,mixed> $properties
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        foreach ($properties as $propertyName => $value) {
            match ($propertyName) {
                $this->name->getAsNamesInDotNotation()        => $object->setName($value),
                $this->description->getAsNamesInDotNotation() => $object->setDescription($value),
                default                                       => throw new InvalidArgumentException("Property not available for update: {$propertyName}"),
            };
        }

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->name,
            $this->description
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
            $this->createAttribute($this->id)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->name)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->description)->readable(true)->filterable()->sortable(),
            $this->createToOneRelationship($this->statementFormDefinition)->readable()->filterable()->sortable(),
            $this->createToOneRelationship($this->procedureUiDefinition)->readable()->filterable()->sortable(),
            $this->createToOneRelationship($this->procedureBehaviorDefinition)->readable()->filterable()->sortable(),
        ];
    }
}
