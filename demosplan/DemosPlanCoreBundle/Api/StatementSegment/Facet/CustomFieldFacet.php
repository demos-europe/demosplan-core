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
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\CustomField\SegmentCustomFieldUsageCounter;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;

/**
 * Counts options for a SEGMENT custom field. Unlike {@see StaticFacetInterface} (tags/assignee/
 * place - a fixed, compile-time-known family of exactly three), custom fields are a dynamic,
 * per-procedure family identified by database-generated ids unknown until runtime - there's
 * nothing to "tag" one class per custom field, so this is a plain injected service rather than
 * an auto-discovered strategy. {@see Provider} dispatches to it explicitly instead of through
 * {@see StaticFacetFactory}.
 *
 * Zero-count options are dropped rather than defaulted to 0 (unlike the static facets) -
 * matches the retired `CustomFieldFilterResponseBuilder::buildOptions()`'s identical behaviour.
 */
final class CustomFieldFacet
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly SegmentCustomFieldUsageCounter $customFieldUsageCounter,
    ) {
    }

    public function supports(string $facet, string $procedureId): bool
    {
        return null !== $this->findConfig($facet, $procedureId);
    }

    /**
     * @param list<Segment> $segments
     * @param list<string>  $selectedIds
     *
     * @return list<FacetResource>
     */
    public function getResources(string $facet, string $procedureId, array $segments, array $selectedIds): array
    {
        $config = $this->findConfig($facet, $procedureId);
        if (null === $config) {
            return [];
        }

        $options = $config->getConfiguration()->getOptions();
        if ([] === $options) {
            return [];
        }

        $counts = $this->customFieldUsageCounter->countOptionUsage($segments, $facet);

        return array_values(array_filter(array_map(
            static fn ($option) => 0 < ($counts[$option->getId()] ?? 0)
                ? FacetResource::create($option->getId(), $option->getLabel(), $counts[$option->getId()], in_array($option->getId(), $selectedIds, true))
                : null,
            $options
        )));
    }

    private function findConfig(string $facet, string $procedureId): ?CustomFieldConfiguration
    {
        $configs = $this->customFieldConfigurationRepository->findCustomFieldConfigurationByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::segment->value,
            $facet,
        );

        return $configs[0] ?? null;
    }
}
