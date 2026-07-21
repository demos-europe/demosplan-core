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
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\AssessmentTableFilter;

class CustomFieldFilterResponseBuilder
{
    private const CF_FILTER_PREFIX = 'customField_';

    public function __construct(
        private readonly CustomFieldConfigurationRepository $cfConfigRepository,
        private readonly CustomFieldStatementCounter $customFieldStatementCounter,
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

        $activeCfFilters = $this->extractActiveCfFilters($userFilters);

        if ([] === $activeCfFilters) {
            return [];
        }

        $cfConfigs = $this->cfConfigRepository->findCustomFieldConfigurationByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::statement->value,
        );

        $regularUserFilters = $this->customFieldFilterResolver->withoutCfFilterKeys($userFilters);

        // Sentinel-free values only, shared across all fields below when building each
        // field's "other active CF filters" constraint (see buildSingleFilterItem()).
        $strippedCfFilters = $this->customFieldFilterResolver->extractActiveCfFilters($userFilters);

        $filterItems = [];

        foreach ($cfConfigs ?? [] as $config) {
            if (!array_key_exists($config->getId(), $activeCfFilters)) {
                continue;
            }

            $item = $this->buildSingleFilterItem(
                $config,
                $procedureId,
                $activeCfFilters,
                $strippedCfFilters,
                $regularUserFilters,
                $search
            );

            if (null !== $item) {
                $filterItems[] = $item;
            }
        }

        return $filterItems;
    }

    /**
     * Deliberately does NOT strip sentinel empty values (unlike
     * CustomFieldFilterResolver::extractActiveCfFilters()) — a field opened via its
     * empty-value sentinel must still be treated as "active" here so its filter item
     * (with fresh option counts) is included in the response. Sentinels are stripped
     * separately, only where they'd otherwise act as query constraints (see
     * $strippedCfFilters in buildFilterItems()).
     *
     * @return array<string, string[]> fieldId => selected option IDs
     */
    private function extractActiveCfFilters(array $userFilters): array
    {
        $active = [];

        foreach ($userFilters as $key => $values) {
            if (str_starts_with($key, self::CF_FILTER_PREFIX)) {
                $active[substr($key, strlen(self::CF_FILTER_PREFIX))] = (array) $values;
            }
        }

        return $active;
    }

    /**
     * @param array<string, string[]> $activeCfFilters    fieldId => raw values (sentinels included), used
     *                                                     to determine selected options for $config's own field
     * @param array<string, string[]> $strippedCfFilters  fieldId => real values only, used to constrain
     *                                                     counts by the OTHER active CF fields
     * @param array<string, mixed>    $regularUserFilters raw assessment table filters, without any
     *                                                     `customField_*` keys
     */
    private function buildSingleFilterItem(
        CustomFieldConfiguration $config,
        string $procedureId,
        array $activeCfFilters,
        array $strippedCfFilters,
        array $regularUserFilters,
        ?string $search,
    ): ?AssessmentTableFilter {
        $fieldId = $config->getId();
        $field = $config->getConfiguration();
        $field->setId($fieldId);

        $fieldOptions = $field->getOptions();

        if ([] === $fieldOptions) {
            return null;
        }

        // Facet exclusion: count options for this field with all OTHER CF filters applied,
        // but not this field's own active selection (mirrors ES aggregation behaviour).
        $constraintFilters = array_filter(
            $strippedCfFilters,
            static fn (string $id): bool => $id !== $fieldId,
            ARRAY_FILTER_USE_KEY
        );

        $optionIds = array_map(static fn ($option) => $option->getId(), $fieldOptions);

        $counts = $this->customFieldStatementCounter->countByField(
            $procedureId,
            $fieldId,
            $optionIds,
            $regularUserFilters,
            $constraintFilters,
            $search
        );

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
