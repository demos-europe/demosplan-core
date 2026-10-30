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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;

interface EntityCustomFieldUsageRemovalStrategyInterface
{
    /**
     * Remove all usages of the custom field from target entities.
     */
    public function removeUsages(string $customFieldId): void;

    /**
     * Remove the deleted options from the values of the target entities.
     * How a value is trimmed depends on the field type, see CustomFieldInterface::removeOptionsFromValue().
     *
     * @param list<string> $deletedOptionIds
     */
    public function removeOptionUsages(CustomFieldInterface $customField, array $deletedOptionIds): void;

    /**
     * Check if this strategy supports the given target entity class.
     */
    public function supports(string $targetEntityClass): bool;
}
