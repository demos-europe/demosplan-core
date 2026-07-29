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

use Elastica\Aggregation\Filter as FilterAggregation;
use Elastica\Aggregation\Nested as NestedAggregation;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Nested;
use Elastica\Query\Term;

/**
 * Builds Elasticsearch nested queries/aggregations against the `customFieldsForIndex` nested
 * mapping (config/packages/fos_elastica.yaml, `statements` index) — one {id, value} object per
 * custom field a statement has a value for. The mapping key matches
 * {@see \demosplan\DemosPlanCoreBundle\Entity\Statement\Statement::getCustomFieldsForIndex()}
 * by name, since FOSElastica's default ORM transformer resolves a mapping property via Symfony's
 * PropertyAccessor (i.e. `getCustomFieldsForIndex()`) — there is no bundle option to point a
 * mapping key at a differently-named getter.
 *
 * `value` is a single `keyword` field that transparently holds either a scalar (singleSelect) or
 * an array (multiSelect) source value — Elasticsearch keyword fields are inherently multi-valued,
 * so a Term match against `value` works identically for both field types without special-casing.
 *
 * The `nested` mapping type (as opposed to `object`) is required so that an `id` + `value` pair is
 * only matched when both belong to the SAME customFieldsForIndex entry — with a plain `object`
 * mapping, ES would flatten `id` and `value` into independent multi-valued arrays at the statement
 * level and a query for fieldA + optionX could spuriously match a statement where fieldA has a
 * different value and optionX happens to belong to fieldB.
 */
class CustomFieldElasticaQueryBuilder
{
    private const PATH = 'customFieldsForIndex';

    /**
     * One clause per field, ANDed by the caller; each clause ORs across that field's selected
     * option IDs (mirrors the "OR within a field, AND across fields" semantics of the filter UI).
     *
     * @param array<string, string[]> $fieldFilters fieldId => selected option IDs
     *
     * @return AbstractQuery[]
     */
    public function buildFieldClauses(array $fieldFilters): array
    {
        $clauses = [];

        foreach ($fieldFilters as $fieldId => $optionIds) {
            $fieldBool = new BoolQuery();
            foreach ($optionIds as $optionId) {
                $fieldBool->addShould($this->buildOptionMatchQuery($fieldId, $optionId));
            }
            $fieldBool->setMinimumShouldMatch(1);
            $clauses[] = $fieldBool;
        }

        return $clauses;
    }

    /**
     * Nested query matching statements with a customFieldsForIndex entry
     * {id: $fieldId, value: $optionId} (or an entry whose array value contains $optionId, for a
     * multiSelect field). For use as a top-level (non-aggregation) query clause only — the ES
     * `nested` type requires exactly one `nested` query hop per path to reach the nested document
     * space; see {@see buildOptionMatchInnerQuery()} for the aggregation-context equivalent.
     */
    public function buildOptionMatchQuery(string $fieldId, string $optionId): Nested
    {
        return (new Nested())->setPath(self::PATH)->setQuery($this->buildOptionMatchInnerQuery($fieldId, $optionId));
    }

    /**
     * Same {id, value} match as {@see buildOptionMatchQuery()}, without the `Nested` query wrapper.
     * Used inside a `Filter` aggregation that already sits under a `Nested` aggregation on the same
     * path — that nested aggregation already moved evaluation into the nested document space, so
     * wrapping this in another `Nested` *query* would try to descend a second, nonexistent nesting
     * level and silently match nothing.
     */
    private function buildOptionMatchInnerQuery(string $fieldId, string $optionId): BoolQuery
    {
        return (new BoolQuery())
            ->addMust(new Term([self::PATH.'.id' => $fieldId]))
            ->addMust(new Term([self::PATH.'.value' => $optionId]));
    }

    /**
     * One Nested aggregation with one Filter sub-aggregation per option (keyed `opt_{index}`,
     * index matching $optionIds' order). A sub-aggregation's doc_count is the number of statements
     * whose customFieldsForIndex entry for $fieldId matches that option — safe to read directly as a
     * per-statement count because CustomFieldValuesList enforces at most one entry per field ID
     * per statement, so no nested entry (and thus no statement) is ever double-counted.
     *
     * @param string[] $optionIds
     */
    public function buildOptionCountsAggregation(string $aggregationName, string $fieldId, array $optionIds): NestedAggregation
    {
        $nestedAggregation = new NestedAggregation($aggregationName, self::PATH);

        foreach (array_values($optionIds) as $index => $optionId) {
            $filterAggregation = new FilterAggregation("opt_{$index}");
            $filterAggregation->setFilter($this->buildOptionMatchInnerQuery($fieldId, $optionId));
            $nestedAggregation->addAggregation($filterAggregation);
        }

        return $nestedAggregation;
    }

    /**
     * One top-level Filter aggregation per field in $fieldsToCount, keyed
     * `customFieldOptionCounts_{fieldId}`, each scoped by its own facet-exclusion filter
     * (matches statements satisfying every OTHER active custom-field filter, but not this
     * field's own selection) and containing {@see buildOptionCountsAggregation()} for that
     * field. Lets a single ES query return option counts for multiple custom fields at once —
     * each field still gets its own correct exclusion state, which is why this can't be done by
     * adding a single top-level query filter (a query only has one filter state; N fields need N
     * different ones).
     *
     * @param array<string, string[]> $fieldsToCount   fieldId => option IDs to count, for every
     *                                                 field this request needs counts for
     * @param array<string, string[]> $activeCfFilters fieldId => selected option IDs, for every
     *                                                 currently active custom-field filter
     *                                                 (including fields in $fieldsToCount) —
     *                                                 used to build each field's own exclusion filter
     *
     * @return array<string, FilterAggregation>
     */
    public function buildFacetedOptionCountsAggregations(array $fieldsToCount, array $activeCfFilters): array
    {
        $aggregations = [];

        foreach ($fieldsToCount as $fieldId => $optionIds) {
            $otherFieldFilters = array_filter(
                $activeCfFilters,
                static fn (string $id): bool => $id !== $fieldId,
                ARRAY_FILTER_USE_KEY
            );

            $exclusionFilter = new BoolQuery();
            foreach ($this->buildFieldClauses($otherFieldFilters) as $clause) {
                $exclusionFilter->addMust($clause);
            }

            $aggregationName = "customFieldOptionCounts_{$fieldId}";
            $facetAggregation = (new FilterAggregation($aggregationName))->setFilter($exclusionFilter);
            $facetAggregation->addAggregation($this->buildOptionCountsAggregation('byOption', $fieldId, $optionIds));

            $aggregations[$aggregationName] = $facetAggregation;
        }

        return $aggregations;
    }
}
