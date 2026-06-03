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

interface CustomFieldOptionRemovalStrategyInterface
{
    public function supports(string $fieldType): bool;

    /**
     * Returns the updated value, or null if the field should be removed entirely.
     */
    public function removeOptionUsage(CustomFieldValue $currentValue, array $deletedOptionIds): ?CustomFieldValue;
}
