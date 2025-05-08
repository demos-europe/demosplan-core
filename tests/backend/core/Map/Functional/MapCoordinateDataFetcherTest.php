<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Functional;

use demosplan\DemosPlanCoreBundle\Exception\ExternalDataFetchException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapCoordinateDataFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapProjectionConverter;
use demosplan\DemosPlanCoreBundle\ValueObject\MapCoordinate;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\AdminLevelCollection;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Geocoder\Provider\Provider;
use Geocoder\Query\ReverseQuery;
use Psr\Log\NullLogger;
use Tests\Base\FunctionalTestCase;

class MapCoordinateDataFetcherTest extends FunctionalTestCase
{
    /**
     * @dataProvider fetchDataProvider
     */
    public function testFetchCoordinateData($in, $out): void
    {
        self::markTestSkipped('This test is skipped because it calls external resources.');

        $sut = $this->createSut(
            $out['postalCode'],
            $out['city'],
            $out['municipalCode'],
            $out['ars'],
            $in['lat'],
            $in['lon']
        );
        $mapCoordinate = $this->createMapCoordinate($in);

        $locationData = $sut->fetchCoordinateData($mapCoordinate);

        self::assertEquals($out['ars'], $locationData->getArs());
        self::assertEquals($out['city'], $locationData->getLocality());
        self::assertEquals($out['municipalCode'], $locationData->getMunicipalCode());
        self::assertEquals($out['postalCode'], $locationData->getPostalCode());
    }

    public function fetchDataProvider()
    {
        return [
            [
                'in'  => [
                    'latMap' => 1061889.1340879,
                    'lonMap' => 7269899.6631521,
                    'lat'    => 54.523336555209,
                    'lon'    => 9.5391123919164,
                ],
                'out' => [
                    'postalCode'    => '24837',
                    'city'          => 'Schleswig',
                    'municipalCode' => '01059075',
                    'ars'           => '010590075075',
                ],
            ],
            [
                'in'  => [
                    'latMap' => 961070.91179295,
                    'lonMap' => 7292446.8204785,
                    'lat'    => 54.640718486421,
                    'lon'    => 8.6334468918629,
                ],
                'out' => [
                    'postalCode'    => '25863',
                    'city'          => 'Langeneß',
                    'municipalCode' => '01054074',
                    'ars'           => '010545459074',
                ],
            ],
        ];
    }

    /**
     * @dataProvider fetchDataExceptionProvider
     */
    public function testFetchCoordinateDataException($in, $out, $exception): void
    {
        self::markTestSkipped('This test is skipped because it calls external resources.');

        $this->expectException($exception);

        $sut = $this->createSut(
            $out['postalCode'],
            $out['city'],
            $out['municipalCode'],
            $out['ars'],
            $in['lat'],
            $in['lon']
        );
        $mapCoordinate = $this->createMapCoordinate($in);

        $sut->fetchCoordinateData($mapCoordinate);
    }

    public function fetchDataExceptionProvider()
    {
        return [
            [
                'in'        => [
                    'latMap' => -1,
                    'lonMap' => -1,
                    'lat'    => 0,
                    'lon'    => 0,
                ],
                'out'       => [
                    'postalCode'    => '',
                    'city'          => '',
                    'municipalCode' => '',
                    'ars'           => '',
                ],
                'exception' => ExternalDataFetchException::class,
            ],
            [
                'in'        => [
                    'latMap' => 1123961070.91179295,
                    'lonMap' => 443567292446.8204785,
                    'lat'    => 0,
                    'lon'    => 0,
                ],
                'out'       => [
                    'postalCode'    => '',
                    'city'          => '',
                    'municipalCode' => '',
                    'ars'           => '',
                ],
                'exception' => InvalidDataException::class,
            ],
        ];
    }

    private function createSut(
        string $postalCode,
        string $city,
        string $municipalCode,
        string $ars,
        $lat,
        $lon,
    ): MapCoordinateDataFetcher {
        $locationService = $this->createMock(LocationService::class);
        $locationService->method('getMunicipalCodes')
            ->with($postalCode, $city)
            ->willReturn([
                'municipalCode' => $municipalCode,
                'ars'           => $ars,
            ]);

        $logger = new NullLogger();

        $nominatim = $this->createMock(Provider::class);
        $address = new NominatimAddress(
            'test',
            new AdminLevelCollection(),
            null,
            null,
            null,
            null,
            $postalCode,
            $city
        );
        $addressCollection = new AddressCollection([$address]);
        $nominatim->method('reverseQuery')
            ->with(ReverseQuery::fromCoordinates($lat, $lon))
            ->willReturn($addressCollection);

        $mapProjectionConverter = self::getContainer()->get(MapProjectionConverter::class);

        return new MapCoordinateDataFetcher($locationService, $logger, $mapProjectionConverter, $nominatim);
    }

    private function createMapCoordinate($in): MapCoordinate
    {
        $mapCoordinate = new MapCoordinate();
        $mapCoordinate->setLatitude($in['latMap']);
        $mapCoordinate->setLongitude($in['lonMap']);
        $mapCoordinate->lock();

        return $mapCoordinate;
    }
}
