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
use demosplan\DemosPlanCoreBundle\Logic\Map\GeoJsonToFeaturesConverter;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapProjectionConverter;
use demosplan\DemosPlanCoreBundle\Logic\Maps\WktToGeoJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\UrlFileReader;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayerTile;
use geoPHP\Geometry\Geometry;
use Illuminate\Support\Collection;
use Intervention\Image\ImageManager;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Base\UnitTestCase;

class GeoJsonToFeaturesConverterTest extends UnitTestCase
{
    /**
     * 1x1 transparent PNG. Used as the fake response body for every tile URL
     * so the test never hits the real WMS service.
     */
    private const TILE_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var GeoJsonToFeaturesConverter */
    protected $sut;

    /**
     * @var string
     */
    private $geoJsonFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $pngBytes = base64_decode(self::TILE_PNG_BASE64, true);
        $mockHttpClient = new MockHttpClient(static fn () => new MockResponse($pngBytes));
        $urlFileReader = new UrlFileReader($mockHttpClient, new NullLogger());

        $container = self::getContainer();
        $this->sut = new GeoJsonToFeaturesConverter(
            $container->get(ImageManager::class),
            $container->get(MapProjectionConverter::class),
            $urlFileReader,
            $container->get(WktToGeoJsonConverter::class),
        );

        $geoJsonFilesDir = DemosPlanPath::getTestPath('backend/core/Map/files/GeoJsonFiles');
        $this->geoJsonFilePath = $geoJsonFilesDir.'/geoJson1.json';
    }

    public function testConversion(): void
    {
        $geoJson = $this->getFileContents($this->geoJsonFilePath);
        $geoJsonObject = Json::decodeToMatchingType($geoJson);
        $features = $this->sut->convert($geoJson);
        $i = 0;
        /** @var \demosplan\DemosPlanCoreBundle\ValueObject\Map\Feature $feature */
        foreach ($features as $feature) {
            $inputFeature = $geoJsonObject->features[$i];
            $this->assertValidGeometry($inputFeature->geometry, $feature->getGeometry());
            if (null !== data_get($inputFeature, 'properties.metadata.printLayers')) {
                $this->assertValidPrintLayers(
                    $inputFeature->properties->metadata->printLayers,
                    $feature->getPrintLayers()
                );
            }

            ++$i;
        }
    }

    /**
     * @param array<int, mixed>           $input
     * @param Collection<int, PrintLayer> $output
     */
    private function assertValidPrintLayers(array $input, Collection $output): void
    {
        $this->assertCount(count($input), $output);
        for ($i = 0, $iMax = count($input); $i < $iMax; ++$i) {
            $this->assertValidPrintLayer($input[$i], $output[$i]);
        }
    }

    private function assertValidPrintLayer(object $input, PrintLayer $output): void
    {
        $this->assertEquals($input->layerMapOrder, $output->getLayerMapOrder());
        $this->assertEquals($input->layerName, $output->getLayerName());
        $this->assertEquals($input->layerTitle, $output->getLayerTitle());
        for ($i = 0, $iMax = count($input->tiles); $i < $iMax; ++$i) {
            $this->assertValidPrintLayerTile($input->tiles[$i], $output->getTiles()[$i]);
        }
    }

    private function assertValidPrintLayerTile(object $input, PrintLayerTile $output): void
    {
        // Position
        $this->assertEquals($input->position->x, $output->getPositionX());
        $this->assertEquals($input->position->y, $output->getPositionY());
        $this->assertEquals($input->position->z, $output->getPositionZ());
        // Viewport
        $this->assertEquals($input->tileExtent[0], $output->getLeft());
        $this->assertEquals($input->tileExtent[1], $output->getBottom());
        $this->assertEquals($input->tileExtent[2], $output->getRight());
        $this->assertEquals($input->tileExtent[3], $output->getTop());

        if (isset($input->tileSize)) {
            $this->assertEquals($input->tileSize, $output->getTileSize());
        }

        $this->assertEquals($input->url, $output->getUrl());
    }

    private function assertValidGeometry(object $input, Geometry $output): void
    {
        $this->assertEquals($input->type, $output->getGeomType());
        $this->assertCount(count($input->coordinates), $output->getComponents());
        for ($i = 0, $iMax = count($input->coordinates); $i < $iMax; ++$i) {
            $this->assertEquals($input->coordinates[$i][0], $output->getComponents()[$i]->getX());
            $this->assertEquals($input->coordinates[$i][1], $output->getComponents()[$i]->getY());
        }
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
