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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldProvider;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\SegmentCustomFieldUsageCounter;

/**
 * Turns the options of a SEGMENT custom field and their counts into facet resources. Unlike
 * {@see StaticFacets} (tags/assignee/place - a fixed, compile-time-known family of exactly three),
 * custom fields are a dynamic, per-procedure family identified by database-generated ids unknown
 * until runtime, so this is a separate service. {@see Provider} dispatches to it explicitly after
 * {@see StaticFacets}.
 *
 * Finding the field and counting its options is custom field logic and lives in
 * `Utils/CustomField`; this class only adapts the result to the facet endpoint.
 * Options no segment holds are left out rather than defaulted to 0 (unlike the static facets).
 */
final class CustomFieldFacet
{
    public function __construct(
        private readonly CustomFieldProvider $customFieldProvider,
        private readonly SegmentCustomFieldUsageCounter $usageCounter,
    ) {
    }

    public function supports(string $facet, string $procedureId): bool
    {
        return null !== $this->findField($facet, $procedureId);
    }

    /**
     * @param list<Segment> $segments
     * @param list<string>  $selectedIds
     *
     * @return list<FacetResource>
     */
    public function getResources(string $facet, string $procedureId, array $segments, array $selectedIds): array
    {
        $field = $this->findField($facet, $procedureId);
        if (null === $field) {
            return [];
        }

        return array_map(
            static fn (array $option): FacetResource => FacetResource::create(
                $option['id'],
                $option['label'],
                $option['count'],
                in_array($option['id'], $selectedIds, true)
            ),
            $this->usageCounter->countOptions($field, $segments)
        );
    }

    private function findField(string $facet, string $procedureId): ?CustomFieldInterface
    {
        return $this->customFieldProvider->findCustomFieldByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::segment->value,
            $facet,
        );
    }
}
