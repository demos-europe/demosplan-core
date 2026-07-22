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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
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

    private const REQUIRED_PERMISSION = 'feature_statements_custom_fields';

    public function __construct(
        private readonly CustomFieldElasticaQueryBuilder $customFieldElasticaQueryBuilder,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    /**
     * Splits $userFilters into custom-field ES clauses (to AND into the query's boolMustFilter)
     * and the remaining filters (with all `customField_*` keys stripped, so the generic per-filter
     * loop in ElasticsearchResultCreator doesn't also try to treat them as plain ES field filters).
     * Returns an empty clause list and $userFilters untouched when the custom-field feature
     * permission is off, so callers don't need to check the permission themselves.
     *
     * @param array<string, mixed> $userFilters
     *
     * @return array{0: AbstractQuery[], 1: array<string, mixed>}
     */
    public function resolveCustomFieldFilter(array $userFilters): array
    {
        if (!$this->permissions->hasPermission(self::REQUIRED_PERMISSION)) {
            return [[], $userFilters];
        }

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
     * Returns CF field filters, keyed by field ID.
     *
     * @param array<string, mixed> $userFilters
     * @param bool                 $stripEmptySentinels When true (default), the empty-string
     *                                                  sentinel posted when a dropdown opens is
     *                                                  filtered out of each field's values, and a
     *                                                  field left with no real values is omitted
     *                                                  entirely — used when building ES query
     *                                                  clauses, where a sentinel-only field must not
     *                                                  produce a clause. When false, sentinels are
     *                                                  kept as-is — used by
     *                                                  {@see CustomFieldFilterResponseBuilder}, which
     *                                                  must still treat a field opened via its
     *                                                  sentinel as "active" so its filter item (with
     *                                                  fresh option counts) is included in the response.
     *
     * @return array<string, string[]> fieldId => selected option IDs
     */
    public function extractActiveCfFilters(array $userFilters, bool $stripEmptySentinels = true): array
    {
        $active = [];

        foreach ($userFilters as $key => $values) {
            if (!str_starts_with($key, self::PREFIX)) {
                continue;
            }

            $values = (array) $values;

            if ($stripEmptySentinels) {
                $values = array_values(array_filter(
                    $values,
                    static fn (mixed $v): bool => '' !== (string) $v
                ));

                if ([] === $values) {
                    continue;
                }
            }

            $active[substr($key, strlen(self::PREFIX))] = $values;
        }

        return $active;
    }
}
