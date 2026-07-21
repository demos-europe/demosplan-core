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
     * multiSelect field).
     */
    public function buildOptionMatchQuery(string $fieldId, string $optionId): Nested
    {
        $inner = (new BoolQuery())
            ->addMust(new Term([self::PATH.'.id' => $fieldId]))
            ->addMust(new Term([self::PATH.'.value' => $optionId]));

        return (new Nested())->setPath(self::PATH)->setQuery($inner);
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
            $filterAggregation->setFilter($this->buildOptionMatchQuery($fieldId, $optionId));
            $nestedAggregation->addAggregation($filterAggregation);
        }

        return $nestedAggregation;
    }
}
