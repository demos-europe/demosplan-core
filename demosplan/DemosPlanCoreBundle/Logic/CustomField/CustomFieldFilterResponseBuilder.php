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

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Logic\Statement\ElasticsearchResultCreator;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\AssessmentTableFilter;

class CustomFieldFilterResponseBuilder
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $cfConfigRepository,
        private readonly ElasticsearchResultCreator $elasticsearchResultCreator,
        private readonly CustomFieldFilterResolver $customFieldFilterResolver,
    ) {
    }

    /**
     * Build AssessmentTableFilter items for every singleSelect/multiSelect CF field
     * belonging to a procedure's statements, with statement counts scoped to the
     * currently active filter combination (facet-exclusion applied per field).
     *
     * @param array<string, mixed> $userFilters Raw filters decoded from the filter hash
     *
     * @return AssessmentTableFilter[]
     */
    public function buildFilterItems(
        string $procedureId,
        bool $isOriginalStatementView,
        array $userFilters,
        ?string $search = null,
    ): array {
        // Authoritative regardless of whether the stored filter hash already carries a (possibly
        // stale) 'original' entry — mirrors how the caller derives $isOriginalStatementView.
        $userFilters['original'] = $isOriginalStatementView ? 'IS NULL' : 'IS NOT NULL';

        $activeCfFilters = $this->customFieldFilterResolver->extractActiveCfFilters($userFilters, stripEmptySentinels: false);

        if ([] === $activeCfFilters) {
            return [];
        }

        $cfConfigs = $this->cfConfigRepository->findCustomFieldConfigurationByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::statement->value,
        );

        $regularUserFilters = $this->customFieldFilterResolver->withoutCfFilterKeys($userFilters);

        // Sentinel-free values only, used both to build each field's own facet-exclusion filter
        // and passed to the counter alongside $fieldsToCount below.
        $strippedCfFilters = $this->customFieldFilterResolver->extractActiveCfFilters($userFilters);

        $relevantConfigs = array_values(array_filter(
            $cfConfigs ?? [],
            static fn (CustomFieldConfiguration $config): bool => array_key_exists($config->getId(), $activeCfFilters)
        ));

        $fieldsToCount = [];
        foreach ($relevantConfigs as $config) {
            $field = $config->getConfiguration();
            $field->setId($config->getId());
            $optionIds = array_map(static fn ($option) => $option->getId(), $field->getOptions());

            if ([] !== $optionIds) {
                $fieldsToCount[$config->getId()] = $optionIds;
            }
        }

        $counts = $this->countForFields(
            $procedureId,
            $fieldsToCount,
            $regularUserFilters,
            $strippedCfFilters,
            $search
        );

        $filterItems = [];

        foreach ($relevantConfigs as $config) {
            $item = $this->buildSingleFilterItem($config, $activeCfFilters, $counts[$config->getId()] ?? []);

            if (null !== $item) {
                $filterItems[] = $item;
            }
        }

        return $filterItems;
    }

    /**
     * Was CustomFieldStatementCounter::countForFields() — folded in since buildFilterItems() was
     * its only caller. Counts every field in $fieldsToCount in a SINGLE Elasticsearch query — one
     * Filter aggregation per field, each with its own facet-exclusion filter context — rather than
     * one query per field, so a request with N active custom-field filters costs one ES round trip
     * instead of N. Reuses ElasticsearchResultCreator::getElasticsearchResult() itself
     * (aggregations-only, size 0) so the counted document set — procedure/original scoping, active
     * regular filters, full-text search, fragment filters — is composed by exactly the same logic
     * as the main statement list, instead of being re-derived here.
     *
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
    private function countForFields(
        string $procedureId,
        array $fieldsToCount,
        array $regularUserFilters,
        array $activeCfFilters,
        ?string $search,
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

    /**
     * @param array<string, string[]> $activeCfFilters fieldId => raw values (sentinels included), used
     *                                                 to determine selected options for $config's own field
     * @param array<string, int>      $counts          optionId => count, already scoped to this field's
     *                                                 facet exclusion by the caller
     */
    private function buildSingleFilterItem(
        CustomFieldConfiguration $config,
        array $activeCfFilters,
        array $counts,
    ): ?AssessmentTableFilter {
        $fieldId = $config->getId();
        $field = $config->getConfiguration();
        $field->setId($fieldId);

        $fieldOptions = $field->getOptions();

        if ([] === $fieldOptions) {
            return null;
        }

        $options = $this->buildOptions($fieldOptions, $counts);

        $selectedOptionIds = $activeCfFilters[$fieldId] ?? [];
        $selected = array_values(
            array_filter(
                $options,
                static fn (array $o): bool => in_array($o['value'], $selectedOptionIds, true)
            )
        );

        $filterItem = new AssessmentTableFilter();
        $filterItem->setName("filter_customField_{$fieldId}");
        $filterItem->setLabel($field->getName());
        $filterItem->setType('customField');
        $filterItem->setAvailableOptions($options);
        $filterItem->setSelectedOptions($selected);
        $filterItem->lock();

        return $filterItem;
    }

    /**
     * @param array<string, int> $counts optionId => statement count
     *
     * @return list<array{count: int, label: string, value: string}>
     */
    private function buildOptions(array $fieldOptions, array $counts): array
    {
        $options = [];

        foreach ($fieldOptions as $option) {
            $count = $counts[$option->getId()] ?? 0;

            if (0 === $count) {
                continue;
            }

            $options[] = [
                'count' => $count,
                'label' => $option->getLabel(),
                'value' => $option->getId(),
            ];
        }

        return $options;
    }
}
