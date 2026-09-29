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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

/**
 * Tallies, per option, how many of the given segments hold that option for one custom
 * field. Handles single-select/radio (scalar value) and multiSelect (array value)
 * uniformly. Takes already-fetched segments rather than querying itself, so callers can
 * scope the segment set however they need (e.g. {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet\Provider}
 * scopes it to "every currently active filter except this field's own").
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
            $customFields = $segment->getCustomFields();
            if (!$customFields instanceof CustomFieldValuesList) {
                continue;
            }

            $customFieldValue = $customFields->findById($customFieldId);
            if (null === $customFieldValue) {
                continue;
            }

            $value = $customFieldValue->getValue();
            $selectedOptionIds = is_array($value) ? $value : [$value];

            foreach ($selectedOptionIds as $optionId) {
                if (is_string($optionId) && '' !== $optionId) {
                    $counts[$optionId] = ($counts[$optionId] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }
}
