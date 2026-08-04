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

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Api\Place\PlaceResource;
use demosplan\DemosPlanCoreBundle\Api\Tag\Resource as TagResource;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment as SegmentEntity;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag as TagEntity;

#[ApiResource(
    shortName: 'StatementSegment',
    operations: [
        new GetCollection(uriTemplate: '/StatementSegment', paginationEnabled: false),
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

    #[ApiFilter(SearchFilter::class, strategy: 'ipartial')]
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

    #[ApiFilter(SearchFilter::class, properties: [
        'parentStatementOfSegment.id'            => 'exact',
        'parentStatementOfSegment.procedure.id'  => 'exact',
    ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?StatementResource $parentStatement = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $assigneeId = null;

    /*#[ApiProperty(readable: true, writable: false)]
    public ?PlaceResource $place = null;


    #[ApiProperty(readable: true, writable: false)]
    public array $tags = [];*/

    #[ApiProperty(readable: true, writable: false)]
    public ?string $deadline = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?array $customFields = null;

    public static function fromEntity(SegmentEntity $segment): self
    {
        $resource = new self();
        $resource->id = $segment->getId();
        $resource->text = $segment->getText();
        $resource->externId = $segment->getExternId();
        $resource->internId = $segment->getInternId();
        $resource->orderInProcedure = $segment->getOrderInProcedure();
        $resource->recommendation = $segment->getRecommendation();
        // $resource->parentStatement = StatementResource::fromEntity($segment->getParentStatementOfSegment());
        // $resource->assigneeId = $segment->getAssigneeId();
        // $resource->place = PlaceResource::fromEntity($segment->getPlace());
        /*$resource->tags = array_values($segment->getTags()->map(
            static fn (TagEntity $tag): TagResource => TagResource::fromEntity($tag)
        )->toArray());*/
        $resource->deadline = $segment->getDeadline()?->format('Y-m-d');
        $resource->customFields = $segment->getCustomFields()?->toJson();

        return $resource;
    }
}
