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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template-extends DplanResourceType<Place>
 *
 * @template-implements UpdatableDqlResourceTypeInterface<Place>
 * @template-implements CreatableDqlResourceTypeInterface<Place>
 *
 * @property-read End                   $name
 * @property-read End                   $description
 * @property-read End                   $sortIndex
 * @property-read ProcedureResourceType $procedure
 */
final class PlaceResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var PlaceRepository
     */
    private $placeRepository;

    public function __construct(PlaceRepository $placeRepository, ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->placeRepository = $placeRepository;
    }

    public static function getName(): string
    {
        return 'Place';
    }

    public function getEntityClass(): string
    {
        return Place::class;
    }

    public function isAvailable(): bool
    {
        // for now places are needed if and only if statements can be segmentated
        return $this->currentUser->hasPermission('area_statement_segmentation');
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        // for now all places can be read by anyone if they are available
        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->procedure->id
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
        $id = $this->createAttribute($this->id)
            ->readable(true)
            ->filterable()
            ->sortable();
        $name = $this->createAttribute($this->name)
            ->readable(true)
            ->filterable()
            ->sortable();
        $description = $this->createAttribute($this->description)
            ->readable();
        $sortIndex = $this->createAttribute($this->sortIndex)
            ->readable(true)
            ->filterable()
            ->sortable();
        // allow filtering by procedure to limit places in facet dropdown
        $procedure = $this->createToOneRelationship($this->procedure)->filterable();

        if ($this->currentUser->hasPermission('area_manage_segment_places')) {
            $id->initializable(true);
            $name->initializable();
            $description->initializable(true);
        }

        return [$id, $name, $description, $sortIndex, $procedure];
    }

    /**
     * @param Place $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->name, [$object, 'setName']);
        $updater->ifPresent($this->description, [$object, 'setDescription']);

        $violations = $this->validator->validate($object);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if ($this->currentUser->hasPermission('area_manage_segment_places')) {
            return $this->toProperties(
                $this->name,
                $this->description,
            );
        }

        return [];
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('area_manage_segment_places')
            && $this->currentProcedureService->getProcedure();
    }

    public function createObject(array $properties): ResourceChange
    {
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();
        $name = $properties[$this->name->getAsNamesInDotNotation()];
        $maxUsedIndex = $this->placeRepository->getMaxUsedIndex($procedure->getId());
        $id = $properties[$this->id->getAsNamesInDotNotation()] ?? null;

        $place = new Place($procedure, $name, $maxUsedIndex + 1, $id);
        $procedure->addSegmentPlace($place);

        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->description, [$place, 'setDescription']);

        $violations = $this->validator->validate($place);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $change = new ResourceChange($place, $this, $properties);
        $change->addEntityToPersist($place);

        return $change;
    }
}
