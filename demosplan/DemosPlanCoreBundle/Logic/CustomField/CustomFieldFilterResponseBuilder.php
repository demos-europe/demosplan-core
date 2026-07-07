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
    public function __construct(
        private readonly CustomFieldConfigurationRepository $cfConfigRepository,
        private readonly CustomFieldStatementCounter $customFieldStatementCounter,
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
    ): array {
        $activeCfFilters = $this->extractActiveCfFilters($userFilters);

        $cfConfigs = $this->cfConfigRepository->findCustomFieldConfigurationByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::statement->value,
        );

        $filterItems = [];

        foreach ($cfConfigs ?? [] as $config) {
            $item = $this->buildSingleFilterItem(
                $config,
                $procedureId,
                $isOriginalStatementView,
                $activeCfFilters
            );

            if (null !== $item) {
                $filterItems[] = $item;
            }
        }

        return $filterItems;
    }

    /**
     * @return array<string, string[]> fieldId => selected option IDs
     */
    private function extractActiveCfFilters(array $userFilters): array
    {
        $prefix = 'customField_';
        $active = [];

        foreach ($userFilters as $key => $values) {
            if (str_starts_with($key, $prefix)) {
                $active[substr($key, strlen($prefix))] = (array) $values;
            }
        }

        return $active;
    }

    private function buildSingleFilterItem(
        CustomFieldConfiguration $config,
        string $procedureId,
        bool $isOriginalStatementView,
        array $activeCfFilters,
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
            $activeCfFilters,
            static fn (string $id): bool => $id !== $fieldId,
            ARRAY_FILTER_USE_KEY
        );

        $counts = $this->customFieldStatementCounter->countByField(
            $procedureId,
            $fieldId,
            $isOriginalStatementView,
            $constraintFilters
        );

        $options = $this->buildOptions($fieldOptions, $counts);

        if ([] === $options) {
            return null;
        }

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
