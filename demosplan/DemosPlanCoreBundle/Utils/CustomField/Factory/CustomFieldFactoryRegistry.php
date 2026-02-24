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

class CustomFieldFactoryRegistry
{
    private array $factories = [];

    public function __construct(iterable $factories = [])
    {
        foreach ($factories as $factory) {
            $this->register($factory);
        }
    }

    public function register(CustomFieldFactoryInterface $factory): void
    {
        $this->factories[] = $factory;
    }

    public function getFactory(string $fieldType): CustomFieldFactoryInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($fieldType)) {
                return $factory;
            }
        }

        throw new InvalidArgumentException("No factory found for field type: {$fieldType}");
    }
}
