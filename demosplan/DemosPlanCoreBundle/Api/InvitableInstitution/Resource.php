<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InvitableInstitution;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\Resource as InstitutionTagResource;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;

/**
 * Read side (list/get) of the institutions that can be tagged. Updates still go through
 * the /2.0 InvitableInstitutionResourceType.
 */
#[ApiResource(
    shortName: 'InvitableInstitution',
    operations: [
        new GetCollection(
            uriTemplate: '/InvitableInstitution',
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 100,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            order: ['createdDate' => 'DESC'],
        ),
        new Get(uriTemplate: '/InvitableInstitution/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: Provider::class,
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(OrderFilter::class, properties: ['createdDate'])]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiFilter(SearchFilter::class, strategy: 'ipartial')]
    #[ApiProperty(readable: true, writable: false)]
    public string $name = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $createdDate = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $participationFeedbackEmailAddress = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?array $customFields = null;

    /** @var list<InstitutionTagResource> */
    #[ApiProperty(readable: true, writable: false)]
    public array $assignedTags = [];

    /**
     * @param list<InstitutionTag> $visibleTags only the tags the current user may read
     */
    public static function fromEntity(Orga $orga, array $visibleTags, bool $withCustomFields): self
    {
        $resource = new self();
        $resource->id = $orga->getId();
        $resource->name = $orga->getName() ?? '';
        $resource->createdDate = $orga->getCreatedDate()->format(DATE_ATOM);
        $resource->participationFeedbackEmailAddress = $orga->getParticipationEmail();
        $resource->customFields = $withCustomFields ? $orga->getCustomFields()?->toJson() : null;
        $resource->assignedTags = array_map(
            static fn (InstitutionTag $tag): InstitutionTagResource => InstitutionTagResource::fromEntity($tag),
            $visibleTags
        );

        return $resource;
    }
}
