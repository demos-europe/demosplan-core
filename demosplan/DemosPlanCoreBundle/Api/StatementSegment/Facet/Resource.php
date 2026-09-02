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
 * One counted option of a segment list facet (a tag, an assignee, a place, or a SEGMENT
 * custom field option). Counts are computed against every currently active filter except
 * the one matching `facet` itself, so picking an option never zeroes out its own count -
 * the same "facet exclusion" behaviour the previous Elasticsearch-based
 * `segments.facets.list` RPC provided, now computed via Doctrine.
 *
 * Only `facet` (a plain control parameter, no Doctrine path) is declared via `parameters:`.
 * The other four are genuinely 2+-level Doctrine paths (`tags.id`,
 * `parentStatementOfSegment.procedure.id`) with no matching relation declared on this flat
 * facet-option DTO's own properties - `ExactFilter` (the `parameters:`-native equality
 * filter) can only auto-join a nested path using metadata precomputed from the *resource's
 * own* PHP properties, which this DTO deliberately doesn't have. `SearchFilter` resolves
 * nested paths by walking the real Doctrine `Segment` entity metadata directly instead, but
 * it's built for the classic `FilterExtension` calling convention (`$context['filters']` as
 * a full `{property: value}` dict) rather than `ParameterExtension`'s per-parameter one
 * (`$context['filters']` as a single value) - the two are not interchangeable, verified
 * empirically. So these four stay on `#[ApiFilter(SearchFilter::class, ...)]` +
 * `FilterExtension`, the combination that actually resolves the joins correctly.
 */
#[ApiResource(
    shortName: 'StatementSegmentFacet',
    operations: [
        new GetCollection(
            uriTemplate: '/StatementSegmentFacet',
            paginationEnabled: false,
            parameters: [
                'facet'        => new QueryParameter(required: true,  constraints: [new NotBlank()]),
                'parentStatementOfSegment.procedure.id' => new QueryParameter(required: true,  constraints: [new NotBlank()]),
                'searchPhrase' => new QueryParameter(),
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
