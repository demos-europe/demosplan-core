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

class SingleSelectOptionRemovalStrategy implements CustomFieldOptionRemovalStrategyInterface
{
    public function supports(string $fieldType): bool
    {
        return 'singleSelect' === $fieldType;
    }

    public function removeOptionUsage(CustomFieldValue $currentValue, array $deletedOptionIds): ?CustomFieldValue
    {
        return in_array($currentValue->getValue(), $deletedOptionIds, true) ? null : $currentValue;
    }
}
