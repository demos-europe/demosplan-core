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
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BasePlaceResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

/**
 * @template-extends DplanResourceType<Place>
 *
 * @property-read End                   $name
 * @property-read End                   $description
 * @property-read End                   $solved
 * @property-read End                   $sortIndex
 * @property-read ProcedureResourceType $procedure
 */
final class PlaceResourceType extends DplanResourceType
{
    public function __construct(
        private readonly PlaceRepository $placeRepository
    ) {
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

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        // for now all places can be read by anyone if they are available
        return [$this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->procedure->id
        )];
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_statement_segmentation',
            'area_manage_segment_places'
        );
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BasePlaceResourceConfigBuilder::class);

        $configBuilder->id
            ->readable()
            ->filterable()
            ->sortable();
        $configBuilder->name
            ->readable(true)
            ->filterable()
            ->sortable()
            ->updatable();
        $configBuilder->description
            ->readable()
            ->updatable();
        $configBuilder->solved
            ->readable()
            ->updatable();
        $configBuilder->sortIndex
            ->readable(true)
            ->filterable()
            ->sortable();

        // allow filtering by procedure to limit places in facet dropdown
        $configBuilder->procedure
            ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
            ->filterable();

        if ($this->currentUser->hasPermission('area_manage_segment_places')) {
            $configBuilder->id->initializable(false, true);
            $configBuilder->name->updatable()->initializable(false, null, true);
            $configBuilder->solved->updatable()->initializable(true);
            $configBuilder->description->updatable()->initializable(true);
        }

        $configBuilder->addConstructorBehavior(
            new FixedConstructorBehavior(
                Paths::place()->procedure->getAsNamesInDotNotation(),
                fn (CreationDataInterface $entityData): array => [$this->currentProcedureService->getProcedureWithCertainty(), []]
            )
        );
        $configBuilder->addConstructorBehavior(
            new FixedConstructorBehavior(
                Paths::place()->sortIndex->getAsNamesInDotNotation(),
                fn (CreationDataInterface $entityData): array => [
                    $this->placeRepository->getMaxUsedIndex($this->currentProcedureService->getProcedureWithCertainty()->getId()) + 1,
                    [Paths::place()->sortIndex->getAsNamesInDotNotation()],
                ]
            )
        );
        $configBuilder->addPostConstructorBehavior(
            new FixedSetBehavior(function (Place $place, EntityDataInterface $entityData): array {
                $procedure = $this->currentProcedureService->getProcedureWithCertainty();
                $procedure->addSegmentPlace($place);

                return [];
            })
        );

        return $configBuilder;
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasAllPermissions('area_manage_segment_places', 'area_statement_segmentation')
            && $this->currentProcedureService->getProcedure();
    }
}
