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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<StatementFieldDefinition>
 *
 * @property-read End $name
 * @property-read End $orderNumber
 * @property-read End $enabled
 * @property-read End $required
 * @property-read StatementFormDefinitionResourceType $formDefinition
 */
final class StatementFieldDefinitionResourceType extends DplanResourceType
{
    protected function getAccessConditions(): array
    {
        return [];
        // todo: allow accessFilter by modelling bidirectional relationship of between StatementFieldDefinition and StatementFormDefinition
        // to ensure related ProcedureType and ProcedureType is available here
    }

    public function getEntityClass(): string
    {
        return StatementFieldDefinition::class;
    }

    /**
     * @param StatementFieldDefinition $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        foreach ($properties as $propertyName => $value) {
            match ($propertyName) {
                $this->enabled->getAsNamesInDotNotation()  => $object->setEnabled($value),
                $this->required->getAsNamesInDotNotation() => $object->setRequired($value),
                default                                    => throw new InvalidArgumentException("Property not available for update: {$propertyName}"),
            };
        }

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(): array
    {
        return [
            $this->enabled->getAsNamesInDotNotation()  => null,
            $this->required->getAsNamesInDotNotation() => null,
        ];
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
        return 'StatementFieldDefinition';
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->name)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->orderNumber)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->enabled)->readable(true)->sortable()->filterable()->updatable(),
            $this->createAttribute($this->required)->readable(true)->sortable()->filterable()->updatable(),
            $this->createToOneRelationship($this->formDefinition)->readable()->sortable()->filterable(),
        ];
    }
}
