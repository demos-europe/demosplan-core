<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\EmailAddress;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;

#[ApiResource(
    shortName: 'EmailAddress',
    operations: [
        new Get(uriTemplate: '/EmailAddress/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: EmailAddressProvider::class,
)]
class EmailAddressResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $fullAddress = '';

    public static function fromEntity(EmailAddress $emailAddress): EmailAddressResource
    {
        $resource = new self();
        $resource->id = $emailAddress->getId();
        $resource->fullAddress = $emailAddress->getFullAddress();

        return $resource;
    }
}
