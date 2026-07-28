<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\AssignableUser;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\User\User as UserEntity;

#[ApiResource(
    shortName: 'AssignableUser',
    operations: [
        new GetCollection(uriTemplate: '/AssignableUser'),
        new Get(uriTemplate: '/AssignableUser/{id}'),
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
    public string $firstname = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $lastname = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $orgaName = '';

    public static function fromEntity(UserEntity $user): self
    {
        $resource = new self();
        $resource->id = $user->getId();
        $resource->firstname = $user->getFirstname();
        $resource->lastname = $user->getLastname();
        $resource->orgaName = $user->getOrga()?->getName() ?? '';

        return $resource;
    }
}
