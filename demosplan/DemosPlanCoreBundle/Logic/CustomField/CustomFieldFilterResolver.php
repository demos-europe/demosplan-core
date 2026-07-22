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

use Elastica\Query\AbstractQuery;

/**
 * Extracts `customField_*` entries from the assessment table's raw filter set and turns them into
 * Elasticsearch query clauses via {@see CustomFieldElasticaQueryBuilder}, so custom-field filtering
 * runs natively inside the same ES query as every other statement filter (no separate Doctrine
 * round trip, no id-list intersection).
 */
class CustomFieldFilterResolver
{
    /**
     * Prefix of the `customField_*` filter keys used throughout the assessment table filter
     * pipeline (request params, filter hash entries, ES filter resolution). Single source of
     * truth — reference this instead of redefining the literal elsewhere.
     */
    public const PREFIX = 'customField_';

    public function __construct(
        private readonly CustomFieldElasticaQueryBuilder $customFieldElasticaQueryBuilder,
    ) {
    }

    /**
     * Splits $userFilters into custom-field ES clauses (to AND into the query's boolMustFilter)
     * and the remaining filters (with all `customField_*` keys stripped, so the generic per-filter
     * loop in ElasticsearchResultCreator doesn't also try to treat them as plain ES field filters).
     *
     * @param array<string, mixed> $userFilters
     *
     * @return array{0: AbstractQuery[], 1: array<string, mixed>}
     */
    public function resolveCustomFieldFilter(array $userFilters): array
    {
        $fieldFilters = $this->extractActiveCfFilters($userFilters);

        return [
            $this->customFieldElasticaQueryBuilder->buildFieldClauses($fieldFilters),
            $this->withoutCfFilterKeys($userFilters),
        ];
    }

    /**
     * @param array<string, mixed> $userFilters
     *
     * @return array<string, mixed>
     */
    public function withoutCfFilterKeys(array $userFilters): array
    {
        return array_filter(
            $userFilters,
            static fn (string $key): bool => !str_starts_with($key, self::PREFIX),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Returns CF field filters with real (non-sentinel) values only.
     * Empty-string sentinels posted when a dropdown opens are stripped so they
     * do not produce ES filter clauses.
     *
     * @param array<string, mixed> $userFilters
     *
     * @return array<string, string[]> fieldId => selected option IDs (never empty arrays)
     */
    public function extractActiveCfFilters(array $userFilters): array
    {
        $active = [];

        foreach ($userFilters as $key => $values) {
            if (!str_starts_with($key, self::PREFIX)) {
                continue;
            }

            $nonEmpty = array_values(array_filter(
                (array) $values,
                static fn (mixed $v): bool => '' !== (string) $v
            ));

            if ([] !== $nonEmpty) {
                $active[substr($key, strlen(self::PREFIX))] = $nonEmpty;
            }
        }

        return $active;
    }
}
