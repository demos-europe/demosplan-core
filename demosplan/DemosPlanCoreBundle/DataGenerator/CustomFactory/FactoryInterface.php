<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory;

/**
 * Interface FactoryInterface.
 *
 * Method signatures for entity factories
 *
 * Factories can be used to quickly generate entities.
 * E.g., assuming an entity User has a corresponding UserFactory,
 * `(new Factory($options))->make(10)` would generate 10 user instances
 * with fake data and return them as a collection.
 */
interface FactoryInterface
{
    /**
     * Create and persist entity instances.
     */
    public function make(int $amount = 1, int $batchSize = 10);
}
