<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment as SegmentEntity;

#[ApiResource(
    shortName: 'StatementSegment',
    operations: [
        new GetCollection(uriTemplate: '/StatementSegment'),
        new Get(uriTemplate: '/StatementSegment/{id}'),
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
    public string $text = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $externId = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $internId = null;

    #[ApiProperty(readable: true, writable: false)]
    public int $orderInProcedure = 0;

    #[ApiProperty(readable: true, writable: false)]
    public string $recommendation = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $parentStatementId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $assigneeId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $placeId = null;

    /** @var list<string> */
    #[ApiProperty(readable: true, writable: false)]
    public array $tagIds = [];

    public static function fromEntity(SegmentEntity $segment): self
    {
        $resource = new self();
        $resource->id = $segment->getId();
        $resource->text = $segment->getText();
        $resource->externId = $segment->getExternId();
        $resource->internId = $segment->getInternId();
        $resource->orderInProcedure = $segment->getOrderInProcedure();
        $resource->recommendation = $segment->getRecommendation();
        $resource->parentStatementId = $segment->getParentStatementOfSegment()->getId();
        $resource->assigneeId = $segment->getAssigneeId();
        $resource->placeId = $segment->getPlaceId();
        $resource->tagIds = array_values(array_filter(
            $segment->getTags()->map(static fn ($tag): ?string => $tag->getId())->toArray()
        ));

        return $resource;
    }
}
