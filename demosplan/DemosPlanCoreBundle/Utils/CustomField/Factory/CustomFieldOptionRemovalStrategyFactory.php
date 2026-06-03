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
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Strategy\CustomFieldOptionRemovalStrategyInterface;

class CustomFieldOptionRemovalStrategyFactory
{
    /**
     * @param iterable<CustomFieldOptionRemovalStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {
    }

    public function createForFieldType(string $fieldType): CustomFieldOptionRemovalStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($fieldType)) {
                return $strategy;
            }
        }

        throw new InvalidArgumentException("No option removal strategy found for field type: {$fieldType}");
    }
}
