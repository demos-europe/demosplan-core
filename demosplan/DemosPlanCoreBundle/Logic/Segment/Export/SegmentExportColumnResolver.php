<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use Psr\Log\LoggerInterface;

readonly class SegmentExportColumnResolver
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function resolve(array $columnsDefinition, array $selectedColumnKeys): array
    {
        $columnsByKey = array_column($columnsDefinition, null, 'key');
        $selectedColumnKeys = $this->pinExternIdToFirstPosition($selectedColumnKeys);
        $selectedColumnKeys = $this->deduplicateSelection($selectedColumnKeys);

        $filteredColumnKeysInSelectionOrder = $this->filterToKnownColumnKeysInSelectionOrder(
            $columnsByKey,
            $selectedColumnKeys
        );
        $this->logMissingColumns(array_diff($selectedColumnKeys, $filteredColumnKeysInSelectionOrder));

        return array_map(
            static fn (string $key): array => $columnsByKey[$key],
            $filteredColumnKeysInSelectionOrder
        );
    }

    private function pinExternIdToFirstPosition(array $selectedColumnKeys): array
    {
        return array_merge(['externId'], $selectedColumnKeys);
    }

    private function deduplicateSelection(array $selectedColumnKeys): array
    {
        return array_values(array_unique($selectedColumnKeys));
    }

    private function filterToKnownColumnKeysInSelectionOrder(array $columnsByKey, array $selectedColumnKeys): array
    {
        return array_values(array_intersect($selectedColumnKeys, array_keys($columnsByKey)));
    }

    private function logMissingColumns(array $missingColumnKeys): void
    {
        if ([] === $missingColumnKeys) {
            return;
        }

        $this->logger->warning(
            'The following selected columns were not found in the column definitions and will be ignored.',
            ['missingColumnKeys' => $missingColumnKeys]
        );
    }
}
