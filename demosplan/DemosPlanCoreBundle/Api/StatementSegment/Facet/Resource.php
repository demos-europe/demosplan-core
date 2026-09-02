<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Represents one option (a tag, assignee, place, or custom field value) with its count for
 * a segment list filter.
 * The currently selected filter's own value is ignored when counting, so picking an option
 * never makes its own count drop to zero.
 * `facet` and `searchPhrase` are simple parameters, so they are declared directly in
 * `parameters:`.
 * The other filters (`tags.id`, `assignee.id`, `place.id`,
 * `parentStatementOfSegment.procedure.id`) need real database joins, so they are declared via
 * `#[ApiFilter(SearchFilter::class)]` instead.
 */
#[ApiResource(
    shortName: 'StatementSegmentFacet',
    operations: [
        new GetCollection(
            uriTemplate: '/StatementSegmentFacet',
            paginationEnabled: false,
            parameters: [
                'facet'                                 => new QueryParameter(required: true, constraints: [new NotBlank()]),
                'parentStatementOfSegment.procedure.id' => new QueryParameter(required: true, constraints: [new NotBlank()]),
                'searchPhrase'                          => new QueryParameter(),
            ],
        ),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: Provider::class,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'tags.id'                               => 'exact',
    'assignee.id'                           => 'exact',
    'place.id'                              => 'exact',
    'parentStatementOfSegment.procedure.id' => 'exact',
])]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $label = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $description = null;

    #[ApiProperty(readable: true, writable: false)]
    public int $count = 0;

    #[ApiProperty(readable: true, writable: false)]
    public bool $selected = false;

    /**
     * Only populated for facets whose options are grouped (currently: tags, grouped by
     * topic). Null for ungrouped facets (assignee, place, custom fields).
     */
    #[ApiProperty(readable: true, writable: false)]
    public ?string $groupId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $groupLabel = null;

    public static function create(
        string $id,
        string $label,
        int $count,
        bool $selected,
        ?string $description = null,
        ?string $groupId = null,
        ?string $groupLabel = null,
    ): self {
        $resource = new self();
        $resource->id = $id;
        $resource->label = $label;
        $resource->count = $count;
        $resource->selected = $selected;
        $resource->description = $description;
        $resource->groupId = $groupId;
        $resource->groupLabel = $groupLabel;

        return $resource;
    }
}
