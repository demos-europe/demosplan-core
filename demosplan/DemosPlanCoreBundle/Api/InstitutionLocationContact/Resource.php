<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InstitutionLocationContact;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;

/**
 * Contact information of the location of an institution, only readable as relationship of another
 * resource. Like the legacy {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionLocationContactResourceType}
 * it has neither a get nor a list endpoint: the `NotExposed` operation only lets API Platform generate the
 * identifier of the relationship and answers a direct request with 404.
 */
#[ApiResource(
    shortName: 'InstitutionLocationContact',
    operations: [
        new NotExposed(uriTemplate: '/InstitutionLocationContact/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
)]
#[ApiFilter(PropertyFilter::class)]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $street = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $postalcode = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $city = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $phone = null;

    /**
     * @param bool $withPhone whether the current user may read the phone number (permission
     *                        `field_organisation_phone`), it is left empty otherwise
     */
    public static function fromEntity(Address $address, bool $withPhone): self
    {
        $resource = new self();
        $resource->id = $address->getId();
        $resource->street = $address->getStreet();
        $resource->postalcode = $address->getPostalcode();
        $resource->city = $address->getCity();
        $resource->phone = $withPhone ? $address->getPhone() : null;

        return $resource;
    }
}
