<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Strategy;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;

class MultiSelectOptionRemovalStrategy implements CustomFieldOptionRemovalStrategyInterface
{
    public function supports(string $fieldType): bool
    {
        return 'multiSelect' === $fieldType;
    }

    public function removeOptionUsage(CustomFieldValue $currentValue, array $deletedOptionIds): ?CustomFieldValue
    {
        $remaining = array_values(
            array_filter(
                $currentValue->getValue(),
                static fn (string $id) => !in_array($id, $deletedOptionIds, true)
            )
        );

        if ([] === $remaining) {
            return null;
        }

        $updated = new CustomFieldValue();
        $updated->setId($currentValue->getId());
        $updated->setValue($remaining);

        return $updated;
    }
}
