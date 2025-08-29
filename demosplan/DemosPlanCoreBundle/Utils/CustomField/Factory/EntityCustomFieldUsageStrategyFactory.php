<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Strategy\EntityCustomFieldUsageRemovalStrategyInterface;

class EntityCustomFieldUsageStrategyFactory
{
    /**
     * @param iterable<EntityCustomFieldUsageRemovalStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {
    }

    public function createUsageRemovalStrategy(string $targetEntityClass): EntityCustomFieldUsageRemovalStrategyInterface
    {
        // ✅ Open/Closed: Auto-discovers strategies, no switch/if needed
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($targetEntityClass)) {
                return $strategy;
            }
        }

        throw new InvalidArgumentException("No entity usage removal strategy found for target entity class: {$targetEntityClass}");
    }
}
