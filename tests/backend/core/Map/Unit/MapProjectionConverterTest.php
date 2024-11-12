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

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapProjectionConverter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use proj4php\Proj;
use proj4php\Proj4php;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Tests\Base\UnitTestCase;

class MapProjectionConverterTest extends UnitTestCase
{
    public const DECIMAL_PRECISION = 0.0000001;

    /** @var MapProjectionConverter */
    protected $sut;

    /**
     * @var Proj
     */
    private $currentProjection;

    /**
     * @var Proj
     */
    private $newProjection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(MapProjectionConverter::class);
        $proj4Transformer = new Proj4php();
        $this->currentProjection = new Proj('EPSG:25832', $proj4Transformer);
        $this->newProjection = new Proj('EPSG:3857', $proj4Transformer);
    }

    public function testConvertGeoJsonToString(): void
    {
        $geoJsonFilesDir = DemosPlanPath::getTestPath('backend/core/Map/files/GeoJsonFiles');
        $currentGeoJson = $this->getFileContents($geoJsonFilesDir.'/geoJson2.json');

        $newGeoJson = $this->sut->convertGeoJsonPolygon(
            $currentGeoJson,
            $this->currentProjection,
            $this->newProjection,
            MapProjectionConverter::STRING_RETURN_TYPE
        );

        $this->assertEquals($this->getExpectedGeoJson(), $newGeoJson);
    }

    public function testConvertGeoJsonToObject(): void
    {
        $geoJsonFilesDir = DemosPlanPath::getTestPath('backend/core/Map/files/GeoJsonFiles');
        $currentGeoJson = $this->getFileContents($geoJsonFilesDir.'/geoJson2.json');

        $newGeoJson = $this->sut->convertGeoJsonPolygon(
            $currentGeoJson,
            $this->currentProjection,
            $this->newProjection,
            MapProjectionConverter::OBJECT_RETURN_TYPE
        );

        $this->assertEquals($this->getExpectedGeoJson(), Json::encode($newGeoJson));
    }

    public function testConvertViewportToString(): void
    {
        $viewport = '441997.41,5923055.13,611330.65,6089742.54';
        $convertedViewport = $this->sut->convertViewport(
            $viewport,
            $this->currentProjection,
            $this->newProjection,
            MapProjectionConverter::STRING_RETURN_TYPE
        );
        $expectedViewport = '904640.92309477,7067292.9633037,1195347.6354542,7350657.5148909';
        $this->assertEquals($expectedViewport, $convertedViewport);
    }

    public function testConvertViewportToArray(): void
    {
        $viewport = '441997.41,5923055.13,611330.65,6089742.54';
        $convertedViewport = $this->sut->convertViewport(
            $viewport,
            $this->currentProjection,
            $this->newProjection,
            MapProjectionConverter::ARRAY_RETURN_TYPE
        );
        $this->assertEqualsWithDelta(904640.9230947, $convertedViewport[0], self::DECIMAL_PRECISION);
        $this->assertEqualsWithDelta(7067292.9633037, $convertedViewport[1], self::DECIMAL_PRECISION);
        $this->assertEqualsWithDelta(1195347.6354542, $convertedViewport[2], self::DECIMAL_PRECISION);
        $this->assertEqualsWithDelta(7350657.5148909, $convertedViewport[3], self::DECIMAL_PRECISION);
    }

    public function testConvertCoordinatesViewport(): void
    {
        $viewport = new CoordinatesViewport(590156.0233245, 5930712.8959695, 600908.04482854, 5941464.9174735);
        $convertedViewport = $this->sut->convertCoordinatesViewport(
            $viewport,
            MapService::EPSG_25832_PROJECTION_LABEL,
            MapService::PSEUDO_MERCATOR_PROJECTION_LABEL,
        );
        $expectedViewport = new CoordinatesViewport(1153242.4985034275, 7079321.2477863515, 1171674.5046008124, 7097063.301828073);
        self::assertEquals($expectedViewport, $convertedViewport);

        $viewport = new CoordinatesViewport(590156.0233245, 5930712.8959695, 600908.04482854, 5941464.9174735);
        $convertedViewport = $this->sut->convertCoordinatesViewport(
            $viewport,
            MapService::EPSG_25832_PROJECTION_LABEL,
            MapService::EPSG_25832_PROJECTION_LABEL,
        );
        $expectedViewport = $viewport;
        self::assertEquals($expectedViewport, $convertedViewport);
    }

    public function testConvertCoordinateToString(): void
    {
        $coordinate = ' 574161.00457201,  6020279.0407189';
        $convertedCoordinate = $this->sut->convertCoordinate(
            $coordinate,
            $this->currentProjection,
            $this->newProjection,
            MapProjectionConverter::STRING_RETURN_TYPE
        );
        $this->assertEquals('1128812.686985,7231944.5026638', $convertedCoordinate);
    }

    public function testConvertCoordinateToArray(): void
    {
        $coordinate = ' 574161.00457201,6020279.0407189';
        $convertedCoordinate = $this->sut->convertCoordinate(
            $coordinate,
            $this->currentProjection,
            $this->newProjection,
            MapProjectionConverter::ARRAY_RETURN_TYPE
        );
        $this->assertEqualsWithDelta(1128812.686985, $convertedCoordinate[0], self::DECIMAL_PRECISION);
        $this->assertEqualsWithDelta(7231944.5026638, $convertedCoordinate[1], self::DECIMAL_PRECISION);
    }

    private function getExpectedGeoJson()
    {
        $geoJsonFilesDir = DemosPlanPath::getTestPath('backend/core/Map/files/GeoJsonFiles');
        $fileContents = $this->getFileContents($geoJsonFilesDir.'/convertedGeoJson2.json');

        return str_replace(["\n", "\t", ' ', "\r"], '', trim($fileContents, ''));
    }

    private function getFileContents(string $fullPath): string
    {
        // uses local file, no need for flysystem
        if (!$fileContents = file_get_contents($fullPath)) {
            throw new FileNotFoundException('File not found in path: '.$fullPath);
        }

        return $fileContents;
    }
}
