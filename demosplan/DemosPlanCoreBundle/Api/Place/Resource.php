<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Place;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place as PlaceEntity;

#[ApiResource(
    shortName: 'Place',
    operations: [
        new GetCollection(uriTemplate: '/Place'),
        new Get(uriTemplate: '/Place/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    //provider: PlaceStateProvider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $name = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $description = '';

    #[ApiProperty(readable: true, writable: false)]
    public bool $solved = false;

    #[ApiProperty(readable: true, writable: false)]
    public bool $locked = false;

    #[ApiProperty(readable: true, writable: false)]
    public int $sortIndex = 0;

    #[ApiProperty(readable: true, writable: false)]
    public string $procedureId = '';

    public static function fromEntity(PlaceEntity $place): self
    {
        $resource = new self();
        $resource->id = $place->getId();
        $resource->name = $place->getName();
        $resource->description = $place->getDescription();
        $resource->solved = $place->getSolved();
        $resource->locked = $place->isLocked();
        $resource->sortIndex = $place->getSortIndex();
        $resource->procedureId = $place->getProcedure()->getId();

        return $resource;
    }
}
