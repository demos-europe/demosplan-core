<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use Tests\Base\UnitTestCase;

class JsonApiEsServiceTest extends UnitTestCase
{
    public function testSort()
    {
        // this is ridiculous, I had to make this method not only `public` but also
        // `static` just to avoid either creating a giant test setup for the injection
        // or a separate class for this small helper method
        $result = JsonApiEsService::sortAndFilterByKeys(
            ['c' => 1, 'a' => 2, 'd' => 0],
            [2 => 'foo', 1 => 'bar', 3 => 'baz']
        );
        $expected = ['c' => 'bar', 'a' => 'foo'];

        self::assertEquals($expected, $result);
    }
}
