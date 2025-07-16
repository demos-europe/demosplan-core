<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Unit;

use demosplan\DemosPlanCoreBundle\Logic\Maps\WktToGeoJsonConverter;
use Tests\Base\UnitTestCase;

class WktToGeoJsonConverterTest extends UnitTestCase
{
    /** @var WktToGeoJsonConverter */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(WktToGeoJsonConverter::class);
    }

    public function testConvertLegacy()
    {
        $textToTest = 'bobject123%7B%22type%22%3A%22FeatureCollection%22%2C%22features%22%3A%5B%7B%22type%22%3A%22Feature%22%2C%22properties%22%3A%7B%7D%2C%22geometry%22%3A%7B%22type%22%3A%22Polygon%22%2C%22coordinates%22%3A%5B%5B%5B581924.70118451%2C5936305.8974907%5D%2C%5B581935.81367851%2C5936292.0068732%5D%2C%5B581935.01992894%2C5936267.7975113%5D%2C%5B581946.92617251%2C5936246.3662728%5D%2C%5B582015.58551044%2C5936276.1318818%5D%2C%5B581982.64490322%2C5936305.1037411%5D%2C%5B581975.1042823%2C5936326.1381048%5D%2C%5B581924.70118451%2C5936305.8974907%5D%5D%5D%7D%2C%22id%22%3A%228469230%22%7D%5D%7Dbobject123';
        $expectedResult = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[581924.70118451,5936305.8974907],[581935.81367851,5936292.0068732],[581935.01992894,5936267.7975113],[581946.92617251,5936246.3662728],[582015.58551044,5936276.1318818],[581982.64490322,5936305.1037411],[581975.1042823,5936326.1381048],[581924.70118451,5936305.8974907]]]},"id":"8469230"}]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = 'bobject123{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[580159.13365612,5935434.5828835],[580393.28977968,5935384.9735352],[580349.63355325,5935176.6142728],[580115.47742969,5935244.0829863],[580125.39929934,5935248.0517342],[580159.13365612,5935434.5828835]]]},"id":"bobjectid228"}]}bobject123';
        $expectedResult = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[580159.13365612,5935434.5828835],[580393.28977968,5935384.9735352],[580349.63355325,5935176.6142728],[580115.47742969,5935244.0829863],[580125.39929934,5935248.0517342],[580159.13365612,5935434.5828835]]]},"id":"bobjectid228"}]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = '["POLYGON((593205.59563797 5944114.5917453,593205.33105411 5944108.5063165,593222.79358903 5944107.1833972,593223.05817289 5944114.0625776,593205.59563797 5944114.5917453))"]';
        $expectedResult = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[593205.59563797,5944114.5917453],[593205.33105411,5944108.5063165],[593222.79358903,5944107.1833972],[593223.05817289,5944114.0625776],[593205.59563797,5944114.5917453]]]}}]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = '["POINT(566311.09051134 6054202.2613138)","POINT(566321.14469812 6054201.4146583)","POINT(566330.03470944 6054201.4146583)","POINT(566344.85140574 6054201.4146583)","POINT(566357.12810342 6054201.4146583)","POINT(566365.59478702 6054200.9913306)","POINT(566371.09814428 6054200.9913306)","POINT(566386.76149602 6054200.9913306)","POINT(566399.46152142 6054200.9913306)","POINT(566423.06242133 6054200.9913306)","POINT(566444.65245805 6054200.1446429)","POINT(566458.62246661 6054200.9913306)","POINT(566436.18577445 6054200.9913306)","POINT(566415.01906545 6054203.5313292)"]';
        $expectedResult = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566311.09051134,6054202.2613138]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566321.14469812,6054201.4146583]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566330.03470944,6054201.4146583]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566344.85140574,6054201.4146583]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566357.12810342,6054201.4146583]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566365.59478702,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566371.09814428,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566386.76149602,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566399.46152142,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566423.06242133,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566444.65245805,6054200.1446429]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566458.62246661,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566436.18577445,6054200.9913306]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[566415.01906545,6054203.5313292]}}]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = '["LINESTRING(592227.23547964 5939998.6828338,592235.70216324 5939982.5432182)"]';
        $expectedResult = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"LineString","coordinates":[[592227.23547964,5939998.6828338],[592235.70216324,5939982.5432182]]}}]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = '["LINESTRING(592276.07978198 5941601.2849985,592278.19645288 5941586.4683022)","LINESTRING(592413.13422275 5941557.3640773,592425.83424815 5941558.4224128)","LINESTRING(592509.4427487 5941484.3389313,592509.97191643 5941471.6389059)","LINESTRING(592209.93381635 5941332.4677942,592211.52131953 5941320.8261043)","LINESTRING(592436.41760265 5941388.0304053,592438.53427355 5941376.9178831)"]';
        $expectedResult = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"LineString","coordinates":[[592276.07978198,5941601.2849985],[592278.19645288,5941586.4683022]]}},{"type":"Feature","properties":{},"geometry":{"type":"LineString","coordinates":[[592413.13422275,5941557.3640773],[592425.83424815,5941558.4224128]]}},{"type":"Feature","properties":{},"geometry":{"type":"LineString","coordinates":[[592509.4427487,5941484.3389313],[592509.97191643,5941471.6389059]]}},{"type":"Feature","properties":{},"geometry":{"type":"LineString","coordinates":[[592209.93381635,5941332.4677942],[592211.52131953,5941320.8261043]]}},{"type":"Feature","properties":{},"geometry":{"type":"LineString","coordinates":[[592436.41760265,5941388.0304053],[592438.53427355,5941376.9178831]]}}]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = '[]';
        $expectedResult = '{"type":"FeatureCollection","features":[]}';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = '';
        $expectedResult = '';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);

        $textToTest = 'something that makes no sense';
        $expectedResult = '';
        $result = $this->sut->convertIfNeeded($textToTest);
        static::assertEquals($expectedResult, $result);
    }
}
