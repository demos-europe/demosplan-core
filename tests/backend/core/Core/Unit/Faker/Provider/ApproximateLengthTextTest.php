<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Faker\Provider;

use demosplan\DemosPlanCoreBundle\Faker\Provider\ApproximateLengthText;
use Faker\Factory;
use Tests\Base\UnitTestCase;

class ApproximateLengthTextTest extends UnitTestCase
{
    public function testApproximateSize()
    {
        $faker = Factory::create('de_DE');
        $faker->addProvider(new ApproximateLengthText($faker));

        $this->assertGreaterThan(100, strlen($faker->textCloseToLength(100)));
        $this->assertGreaterThan(1000, strlen($faker->textCloseToLength(1000)));
        $this->assertGreaterThan(10000, strlen($faker->textCloseToLength(10000)));
    }
}
