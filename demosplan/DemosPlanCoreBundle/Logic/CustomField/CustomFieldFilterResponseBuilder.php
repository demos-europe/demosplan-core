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

        $counts = $this->customFieldStatementCounter->countForFields(
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
