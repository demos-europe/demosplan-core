<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\DemosFixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
abstract class TestFixture extends DemosFixture implements FixtureGroupInterface
{
    /**
     * This method must return an array of groups
     * on which the implementing class belongs to.
     *
     * @return string[]
     */
    public static function getGroups(): array
    {
        return ['TestData'];
    }
}
