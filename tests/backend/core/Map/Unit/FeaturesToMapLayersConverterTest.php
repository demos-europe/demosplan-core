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

use demosplan\DemosPlanCoreBundle\Logic\Map\FeaturesToMapLayersConverter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\Feature;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayerTile;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayerTilePosition;
use geoPHP\Geometry\Point;
use Intervention\Image\ImageManager;
use Tests\Base\UnitTestCase;
use Illuminate\Support\Collection;

use function imagecolorallocate;

class FeaturesToMapLayersConverterTest extends UnitTestCase
{
    /** @var FeaturesToMapLayersConverter */
    protected $sut;

    /** @var ImageManager */
    private $imageManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(FeaturesToMapLayersConverter::class);
        $this->imageManager = self::$container->get(ImageManager::class);
    }

    public function testConversion(): void
    {
        $printLayer1 = new PrintLayer(
            true, // is BaseLayer
            $this->createPrintLayerTiles(1),
            1,
            'printLayerName1',
            'printLayerTitle1',
            DemosPlanPath::getTemporaryPath('test-maps/'),
        );

        $printLayer2 = new PrintLayer(
            false, // is not BaseLayer
            $this->createPrintLayerTiles(2),
            2,
            'printLayerName2',
            'printLayerTitle2',
            DemosPlanPath::getTemporaryPath('test-maps/'),
        );

        $printLayer3 = new PrintLayer(
            false, // is not BaseLayer
            $this->createPrintLayerTiles(3),
            3,
            'printLayerName3',
            'printLayerTitle3',
            DemosPlanPath::getTemporaryPath('test-maps/'),
        );

        $printLayer4 = new PrintLayer(
            false, // is not not BaseLayer
            $this->createPrintLayerTiles(4),
            4,
            'printLayerName4',
            'printLayerTitle4',
            DemosPlanPath::getTemporaryPath('test-maps/'),
        );

        $feature1 = new Feature(
            new Collection([$printLayer1, $printLayer2]),
            new CoordinatesViewport(567.890, 123.456, 789.012, 901.234),
            new Point(567.890, 123.456)
        );

        $feature2 = new Feature(
            new Collection([$printLayer3, $printLayer4]),
            new CoordinatesViewport(567.890, 789.012, 123.456, 901.234),
            new Point(123.456, 567.890)
        );

        $mapLayers = $this->sut->convert(
            new Collection([$feature1, $feature2])
        );

        $this->assertCount(4, $mapLayers);
        $this->assertValidMapLayer($printLayer1, $mapLayers[0]);
        $this->assertValidMapLayer($printLayer2, $mapLayers[1]);
        $this->assertValidMapLayer($printLayer3, $mapLayers[2]);
        $this->assertValidMapLayer($printLayer4, $mapLayers[3]);
        // PrintLayer files need to be removed once the MapLayer is created
        $this->assertFalse(is_dir(DemosPlanPath::getTemporaryPath('test-maps/')));
    }

    private function assertValidMapLayer(PrintLayer $printLayer, MapLayer $mapLayer): void
    {
        $this->assertEquals($printLayer->getLayerTitle(), $mapLayer->getTitle());
        $this->assertEquals($printLayer->getLeft(), $mapLayer->getLeft());
        $this->assertEquals($printLayer->getBottom(), $mapLayer->getBottom());
        $this->assertEquals($printLayer->getRight(), $mapLayer->getRight());
        $this->assertEquals($printLayer->getTop(), $mapLayer->getTop());
        $this->assertFalse(is_dir($printLayer->getImagesDirectoryPath()));
    }

    /**
     * @return Collection<int, PrintLayerTile>
     */
    private function createPrintLayerTiles(int $printLayerIdx): Collection
    {
        $gdImage = imagecreate(256, 256);
        imagecolorallocate($gdImage, 0, 0, 0);

        $printLayerTiles1 = new PrintLayerTile(
            "layerTile$printLayerIdx",
            DemosPlanPath::getTemporaryPath()."test-maps/$printLayerIdx/",
            new PrintLayerTilePosition(1, 1, $printLayerIdx),
            new CoordinatesViewport(123.456, 789.012, 345.678, 901.234),
            256,
            'url-1-1',
            $this->imageManager->make($gdImage)
        );

        $printLayerTiles2 = new PrintLayerTile(
            'layerTile2',
            DemosPlanPath::getTemporaryPath('test-maps/'),
            new PrintLayerTilePosition(1, 2, $printLayerIdx),
            new CoordinatesViewport(901.234, 567.890, 123.456, 789.012),
            256,
            'url-1-2',
            $this->imageManager->make($gdImage)
        );

        return new Collection([
            $printLayerTiles1, $printLayerTiles2,
        ]);
    }
}
