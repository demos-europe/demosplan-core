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

interface EntityCustomFieldUsageRemovalStrategyInterface
{
    /**
     * Remove all usages of the custom field from target entities.
     */
    public function removeUsages(string $customFieldId): void;

    // Option-specific removal method
    public function removeOptionUsages(string $customFieldId, array $deletedOptionIds): void;

    /**
     * Check if this strategy supports the given target entity class.
     */
    public function supports(string $targetEntityClass): bool;
}
