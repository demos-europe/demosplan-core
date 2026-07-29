<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Orga;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;

#[ApiResource(
    shortName: 'Orga',
    operations: [
        new Get(uriTemplate: '/Orga/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: Provider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $name = '';

    public static function fromEntity(OrgaInterface $orga): self
    {
        $resource = new self();
        $resource->id = $orga->getId();
        $resource->name = $orga->getName() ?? '';

        return $resource;
    }
}
