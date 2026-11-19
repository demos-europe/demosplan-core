<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InvitableToeb;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Api\InstitutionLocationContact\Resource as InstitutionLocationContactResource;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\Resource as InstitutionTagResource;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;

/**
 * The institutions that can be invited to the current procedure. Read side of the legacy
 * {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InvitablePublicAgencyResourceType}, with the same attribute names.
 *
 * The default order uses the property of the entity (`name`), not the name of the attribute (`legalName`).
 */
#[ApiResource(
    shortName: 'InvitableToeb',
    operations: [
        new GetCollection(
            uriTemplate: '/InvitableToeb',
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 100,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            order: ['name' => 'ASC'],
        ),
        new Get(uriTemplate: '/InvitableToeb/{id}'),
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
    public string $legalName = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $participationFeedbackEmailAddress = null;

    /**
     * True if at least one user of the institution is not deleted and holds an institution role in the
     * current customer, see {@see Orga::hasActiveInstitutionUsers()}.
     */
    #[ApiProperty(readable: true, writable: false)]
    public bool $hasActiveUsers = false;

    /** @var list<InstitutionLocationContactResource> */
    #[ApiProperty(readable: true, writable: false)]
    public array $locationContacts = [];

    /** @var list<InstitutionTagResource> */
    #[ApiProperty(readable: true, writable: false)]
    public array $assignedTags = [];

    /**
     * @param list<InstitutionTag> $visibleTags  only the tags the current user may read
     * @param bool                 $canReadPhone whether the phone number of the location contacts is exposed
     */
    public static function fromEntity(Orga $orga, array $visibleTags, bool $canReadPhone): self
    {
        $resource = new self();
        $resource->id = $orga->getId();
        $resource->legalName = $orga->getName() ?? '';
        $resource->participationFeedbackEmailAddress = $orga->getParticipationEmail();
        $resource->hasActiveUsers = $orga->hasActiveInstitutionUsers();
        $resource->locationContacts = array_map(
            static fn (Address $address): InstitutionLocationContactResource => InstitutionLocationContactResource::fromEntity($address, $canReadPhone),
            $orga->getAddresses()->getValues()
        );
        $resource->assignedTags = array_map(
            static fn (InstitutionTag $tag): InstitutionTagResource => InstitutionTagResource::fromEntity($tag),
            $visibleTags
        );

        return $resource;
    }
}
