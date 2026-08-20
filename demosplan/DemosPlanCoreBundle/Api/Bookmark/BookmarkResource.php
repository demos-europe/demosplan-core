<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Bookmark;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use Symfony\Component\Validator\Constraints as Assert;
use Webmozart\Assert\Assert as TypeAssert;

/**
 * A named segment list view: the referenced stored query holds the filters, the chosen columns, their
 * order and the sorting, this resource adds the name the user gave it.
 *
 * Writes carry only `name` and `queryHash`. The view itself is already persisted as the query the
 * list URL points at, so saving one means naming a hash the user is currently on - there is nothing
 * to re-validate or re-hash here. The read side additionally exposes the query's contents, so the
 * frontend can render a filter preview and mark the active view without a second request.
 */
#[ApiResource(
    shortName: 'Bookmark',
    operations: [
        new GetCollection(uriTemplate: '/Bookmark', paginationEnabled: false),
        new Get(uriTemplate: self::ITEM_URI_TEMPLATE),
        new Post(
            uriTemplate: '/Bookmark',
            validationContext: ['groups' => ['bookmark:create']],
            read: false,
            processor: BookmarkProcessor::class,
        ),
        new Patch(
            uriTemplate: self::ITEM_URI_TEMPLATE,
            validationContext: ['groups' => ['bookmark:update']],
            processor: BookmarkProcessor::class,
        ),
        new Delete(
            uriTemplate: self::ITEM_URI_TEMPLATE,
            output: false,
            read: false,
            deserialize: false,
            processor: BookmarkProcessor::class,
        ),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: BookmarkProvider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class BookmarkResource
{
    private const ITEM_URI_TEMPLATE = '/Bookmark/{id}';

    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: true)]
    #[Assert\NotBlank(message: 'A name is required to create a bookmark.', groups: ['bookmark:create'])]
    #[Assert\Length(max: 255, maxMessage: 'A bookmark name may not exceed {{ limit }} characters.')]
    public ?string $name = null;

    /**
     * The hash of the stored query this bookmark points at, which is what the segment list URL ends
     * with. Writable, so a PATCH can repoint an existing bookmark at the view the user is now on.
     */
    #[ApiProperty(readable: true, writable: true)]
    #[Assert\NotBlank(message: 'A queryHash is required to create a bookmark.', groups: ['bookmark:create'])]
    public ?string $queryHash = null;

    /**
     * @var array the filter of the referenced query, in the format the JSON:API implementation uses
     */
    #[ApiProperty(readable: true, writable: false)]
    public array $filter = [];

    #[ApiProperty(readable: true, writable: false)]
    public ?string $searchPhrase = null;

    /**
     * @var array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string}
     */
    #[ApiProperty(readable: true, writable: false)]
    public array $viewSettings = [];

    /**
     * Used by the provider and by the processor after a write, so both answer with the same shape.
     *
     * The stored query is asserted to be a segment list one rather than checked gracefully: only
     * {@see BookmarkAccessChecker::getAccessConditions()} hands entities to this method, and it filters
     * on that format in the query itself, so anything else here is a programming error rather than bad
     * input.
     */
    public static function fromEntity(Bookmark $bookmark): self
    {
        $hashedQuery = $bookmark->getFilterSet();
        $storedQuery = $hashedQuery->getStoredQuery();
        TypeAssert::isInstanceOf($storedQuery, SegmentListQuery::class);

        $resource = new self();
        $resource->id = $bookmark->getId();
        $resource->name = $bookmark->getName();
        $resource->queryHash = $hashedQuery->getHash();
        $resource->filter = $storedQuery->getFilter();
        $resource->searchPhrase = $storedQuery->getSearchPhrase();
        $resource->viewSettings = $storedQuery->getViewSettings();

        return $resource;
    }
}
