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

use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet\Resource as FacetResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

/**
 * One static segment-list facet (tags, assignee, or place). Implementations are auto-discovered
 * via the `statement_segment_static_facet` service tag and dispatched by
 * {@see StaticFacetFactory}, mirroring how
 * {@see \demosplan\DemosPlanCoreBundle\Utils\CustomField\Strategy\EntityCustomFieldUsageRemovalStrategyInterface}
 * is wired for custom-field usage removal - adding a new facet means adding a new class here,
 * not editing {@see Provider}.
 */
interface StaticFacetInterface
{
    public function supports(string $facet): bool;

    /**
     * The value(s) one segment has for this facet - always a list, even when there's just one
     * or none, so callers can loop the same way regardless of which facet it is.
     *
     * @return iterable<object>
     */
    public function getValues(Segment $segment): iterable;

    /**
     * Every option that exists for this facet in the current procedure, regardless of whether
     * any segment currently references it - so an option with zero matches still shows up with
     * `count: 0` instead of disappearing.
     *
     * @return array<string, array{label: string, groupId: ?string, groupLabel: ?string}>
     */
    public function getFullOptionSet(): array;

    /**
     * Extra synthetic options beyond the enumerated full option set - e.g. assignee's
     * "unassigned" pseudo-option for segments with nobody assigned. Empty for facets that don't
     * need one.
     *
     * @param list<Segment> $segments    every segment matching the currently active filters
     * @param list<string>  $selectedIds currently selected option ids for this facet
     *
     * @return list<FacetResource>
     */
    public function getExtraResources(array $segments, array $selectedIds): array;
}
