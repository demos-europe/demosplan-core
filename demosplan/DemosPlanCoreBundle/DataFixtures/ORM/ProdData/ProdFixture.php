<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\DemosFixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

abstract class ProdFixture extends DemosFixture implements FixtureGroupInterface
{
    /**
     * This method must return an array of groups
     * on which the implementing class belongs to.
     *
     * @return string[]
     */
    public static function getGroups(): array
    {
        return ['ProdData'];
    }
}
