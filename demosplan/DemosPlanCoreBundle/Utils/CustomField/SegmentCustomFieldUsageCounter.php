<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

/**
 * Tallies, per option, how many of the given segments hold that option for one custom field.
 * Handles single select (scalar value) and multi select (list value) uniformly, see
 * {@see \demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList::getOptionIds()}.
 *
 * Takes already-fetched segments rather than querying itself, so callers can scope the segment set
 * however they need (e.g. the segment facet scopes it to "every currently active filter except
 * this field's own").
 */
class SegmentCustomFieldUsageCounter
{
    /**
     * @param Segment[] $segments
     *
     * @return array<string, int> optionId => count
     */
    public function countOptionUsage(array $segments, string $customFieldId): array
    {
        $counts = [];

        foreach ($segments as $segment) {
            foreach ($segment->getCustomFields()?->getOptionIds($customFieldId) ?? [] as $optionId) {
                $counts[$optionId] = ($counts[$optionId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param Segment[] $segments
     *
     * @return list<array{id: string, label: string, count: int}> the options that at least one of the segments
     *                                                            holds, in the order they are configured
     */
    public function countOptions(CustomFieldInterface $customField, array $segments): array
    {
        $counts = $this->countOptionUsage($segments, $customField->getId());

        $options = [];
        foreach ($customField->getOptions() as $option) {
            $count = $counts[$option->getId()] ?? 0;
            if (0 < $count) {
                $options[] = ['id' => $option->getId(), 'label' => $option->getLabel(), 'count' => $count];
            }
        }

        return $options;
    }
}
