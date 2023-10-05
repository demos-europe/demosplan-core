<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use stdClass;
use Tests\Base\UnitTestCase;

use const NAN;

class JsonTest extends UnitTestCase
{
    public function testSuccesfulEncode(): void
    {
        $input = ['foo' => 'bar', 'baz' => 42];
        $output = '{"foo":"bar","baz":42}';

        self::assertEquals($output, Json::encode($input));
    }

    public function testFailingEncode(): void
    {
        $this->expectException(JsonException::class);

        Json::encode(NAN);
    }

    public function testSuccesfulDecodeToArray(): void
    {
        $input = '{"foo":"bar","baz":42}';
        $output = ['foo' => 'bar', 'baz' => 42];

        self::assertEquals($output, Json::decodeToArray($input));
    }

    /**
     * @dataProvider matchingTypeProvider
     */
    public function testSuccesfulDecodeToMatchingType($input, $output): void
    {
        self::assertEquals($output, Json::decodeToMatchingType($input));
    }

    public function matchingTypeProvider()
    {
        return [
            ['"42"', 42],
            ['"foo"', 'foo'],
            ['[]', []],
            ['{}', new stdClass()],
        ];
    }

    public function testFailingDecode()
    {
        $this->expectException(JsonException::class);

        Json::decodeToArray('forty: 2');
    }
}
