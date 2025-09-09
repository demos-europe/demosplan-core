<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Doctrine;

use demosplan\DemosPlanCoreBundle\Doctrine\Generator\NCNameGenerator;
use Doctrine\ORM\EntityManager;
use Tests\Base\UnitTestCase;

class NCNameGeneratorTest extends UnitTestCase
{
    public function testNCNameGenerator()
    {
        $generator = new NCNameGenerator();
        // test 1000 generated Ids
        for ($i = 0; $i < 1000; ++$i) {
            $uuid = $generator->generate($this->getMock(EntityManager::class), 'anything');
            if (1 === preg_match('/[\d]/', $uuid[0])) {
                self::fail('invalid NCName generated');
            }
            self::assertTrue(true);
        }
        self::assertTrue(true);
    }
}
