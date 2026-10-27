<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InstitutionTag;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTagCategory\Resource as InstitutionTagCategoryResource;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;

#[ApiResource(
    shortName: 'InstitutionTag',
    operations: [
        new Get(uriTemplate: '/InstitutionTag/{id}'),
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

    #[ApiProperty(readable: true, writable: false)]
    public ?InstitutionTagCategoryResource $category = null;

    public static function fromEntity(InstitutionTag $tag): self
    {
        $resource = new self();
        $resource->id = $tag->getId();
        $resource->name = $tag->getLabel();
        $resource->category = InstitutionTagCategoryResource::fromEntity($tag->getCategory());

        return $resource;
    }
}
