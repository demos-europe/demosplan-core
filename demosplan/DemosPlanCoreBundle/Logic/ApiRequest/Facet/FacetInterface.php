<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet;

use demosplan\DemosPlanCoreBundle\ResourceTypes\ClaimResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterType;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\SortMethodInterface;

/**
 * @template TItem of object
 */
interface FacetInterface
{
    /**
     * The translation key that is used to show a name for the facet as a whole.
     */
    public function getFacetNameTranslationKey(): string;

    /**
     * @return string The name of the resource type items correspond to. Must be the name of
     *                a resource type implementing {@link ResourceTypeInterface}&lt;TItem&gt;.
     */
    public function getItemsResourceType(): string;

    /**
     * The condition to load the items filled into
     * {@link AggregationFilterType::$aggregationFilterItems}.
     *
     * @return array<int,FunctionInterface<bool>>
     */
    public function getRootItemsLoadConditions(): array;

    /**
     * @param TItem $item
     */
    public function getItemIdentifier(object $item): string;

    /**
     * @param TItem $item
     */
    public function getItemTitle(object $item): string;

    /**
     * An optional description that can be shown for each specific item, e.g. a description.
     *
     * Returns `null` if this instance has not been configured to return a specific
     * description.
     *
     * May return `null` or an empty string, if this instance has been configured to use a property
     * of the given `$item` but this property is set to `null`/an empty string.
     *
     * @param TItem $item
     */
    public function getItemDescription(object $item): ?string;

    /**
     * Determines how filters/conditions accessing the items should be constructed.
     *
     * E.g. if this instance is a facet of {@link StatementResourceType::$tags}
     * ({@link StatementResourceType} being the context and {@link TagResourceType
     * being the type of the items) then this method would return `true`, because the
     * `tags` property is a to-many relationship from `Statement` to `Tag`. As an effect when
     * filtering for `Statement` resources using its `tags` property you must use
     * operators that apply to a list of `Tag` resources.
     *
     * Likewise, if this instance is a facet of {@link StatementResourceType::$assignee} then
     * this method would return `false`. As an effect when filtering for
     * `Statement` resources using its `assignee` property you must use operators that
     * apply to a single {@link ClaimResourceType} instance or `null`.
     */
    public function isItemToManyRelationship(): bool;

    /**
     * Determines if the sum resources that are not covered by any item should be shown
     * and thus if it should be possible to filter for resources that do not have anything
     * set in the property represented by this instance.
     */
    public function isMissingResourcesSumVisible(): bool;

    /**
     * @return array<int, SortMethodInterface>
     */
    public function getItemsSortMethods(): array;
}
