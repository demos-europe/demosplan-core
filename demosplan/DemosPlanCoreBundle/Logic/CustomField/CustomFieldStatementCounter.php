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
 * Counts every field passed in in a SINGLE Elasticsearch query — one Filter aggregation per
 * field, each with its own facet-exclusion filter context — rather than one query per field, so
 * a request with N active custom-field filters costs one ES round trip instead of N. Reuses
 * ElasticsearchResultCreator::getElasticsearchResult() itself (aggregations-only, size 0) so the
 * counted document set — procedure/original scoping, active regular filters, full-text search,
 * fragment filters — is composed by exactly the same logic as the main statement list, instead
 * of being re-derived here.
 */
class CustomFieldStatementCounter
{
    public function __construct(
        private readonly ElasticsearchResultCreator $elasticsearchResultCreator,
    ) {
    }

    /**
     * @param array<string, string[]> $fieldsToCount      fieldId => option IDs to count
     * @param array<string, mixed>    $regularUserFilters raw assessment table filters (e.g. `original`,
     *                                                    `institution`, ...), without any `customField_*` keys
     * @param array<string, string[]> $activeCfFilters    fieldId => selected option IDs, for EVERY currently
     *                                                    active custom-field filter (including fields in
     *                                                    $fieldsToCount) — used to build each field's own
     *                                                    facet-exclusion filter
     *
     * @return array<string, array<string, int>> fieldId => (optionId => count)
     */
    public function countForFields(
        string $procedureId,
        array $fieldsToCount,
        array $regularUserFilters,
        array $activeCfFilters,
        ?string $search = null,
    ): array {
        if ([] === $fieldsToCount) {
            return [];
        }

        $result = $this->elasticsearchResultCreator->getElasticsearchResult(
            $regularUserFilters,
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
            $fieldsToCount,
            $activeCfFilters,
        );

        $esAggregations = $result->getAggregations();
        $counts = [];

        foreach ($fieldsToCount as $fieldId => $optionIds) {
            $buckets = $esAggregations["customFieldOptionCounts_{$fieldId}"]['byOption'] ?? [];
            $counts[$fieldId] = [];

            foreach (array_values($optionIds) as $index => $optionId) {
                $counts[$fieldId][$optionId] = (int) ($buckets["opt_{$index}"]['doc_count'] ?? 0);
            }
        }

        return $counts;
    }
}
