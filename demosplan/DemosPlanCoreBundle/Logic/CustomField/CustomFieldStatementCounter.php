<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\CustomField;

use demosplan\DemosPlanCoreBundle\Logic\Statement\ElasticsearchResultCreator;

/**
 * Returns statement counts per custom-field option, scoped to the assessment table's currently
 * active filter combination (facet exclusion: a field's own selection is left out of its own
 * count query so its options keep counting the full applicable universe, mirroring how the other
 * ES-backed filters in {@see ElasticsearchResultCreator} behave).
 *
 * Reuses ElasticsearchResultCreator::getElasticsearchResult() itself (aggregations-only, size 0)
 * so the counted document set — procedure/original scoping, active regular filters, full-text
 * search, fragment filters — is composed by exactly the same logic as the main statement list,
 * instead of being re-derived here.
 */
class CustomFieldStatementCounter
{
    public function __construct(
        private readonly ElasticsearchResultCreator $elasticsearchResultCreator,
    ) {
    }

    /**
     * @param string[]                $optionIds          option IDs of the field, as already known by the caller
     * @param array<string, mixed>    $regularUserFilters raw assessment table filters (e.g. `original`,
     *                                                    `institution`, ...), without any `customField_*` keys
     * @param array<string, string[]> $otherCfFilters     fieldId => selectedOptionIds[], for every OTHER
     *                                                    active custom-field filter (excluding $fieldId itself)
     *
     * @return array<string, int> optionId => count
     */
    public function countByField(
        string $procedureId,
        string $fieldId,
        array $optionIds,
        array $regularUserFilters,
        array $otherCfFilters = [],
        ?string $search = null,
    ): array {
        if ([] === $optionIds) {
            return [];
        }

        $mergedFilters = $regularUserFilters;
        foreach ($otherCfFilters as $otherFieldId => $otherOptionIds) {
            $mergedFilters[CustomFieldFilterResolver::PREFIX.$otherFieldId] = $otherOptionIds;
        }

        $result = $this->elasticsearchResultCreator->getElasticsearchResult(
            $mergedFilters,
            $procedureId,
            $search ?? '',
            null,
            0,
            1,
            [],
            true,
            1,
            false,
            [],
            $fieldId,
            $optionIds,
        );

        $buckets = $result->getAggregations()['customFieldOptionCounts'] ?? [];

        $counts = [];
        foreach (array_values($optionIds) as $index => $optionId) {
            $counts[$optionId] = (int) ($buckets["opt_{$index}"]['doc_count'] ?? 0);
        }

        return $counts;
    }
}
