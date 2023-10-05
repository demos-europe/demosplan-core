<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use EDT\JsonApi\ResourceTypes\PropertyBuilder;

trait ResourcePropertyEventSubscriberTrait
{
    /**
     * @param list<PropertyBuilder> $propertyBuilders
     * @param non-empty-string      $propertyName
     *
     * @return PropertyBuilder
     */
    protected function getPropertyBuilder(array $propertyBuilders, string $propertyName): ?PropertyBuilder
    {
        foreach ($propertyBuilders as $propertyBuilder) {
            if ($propertyBuilder->getName() === $propertyName) {
                return $propertyBuilder;
            }
        }

        return null;
    }
}
