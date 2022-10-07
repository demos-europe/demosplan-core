<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Unit;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanMapBundle\Logic\PrintLayerToMapLayerConverter;
use demosplan\DemosPlanMapBundle\ValueObject\CoordinatesViewport;
use demosplan\DemosPlanMapBundle\ValueObject\PrintLayer;
use demosplan\DemosPlanMapBundle\ValueObject\PrintLayerTile;
use demosplan\DemosPlanMapBundle\ValueObject\PrintLayerTilePosition;
use Intervention\Image\ImageManager;
use Tests\Base\UnitTestCase;
use Tightenco\Collect\Support\Collection;
use function imagecolorallocate;

class PrintLayerToMapLayerConverterTest extends UnitTestCase
{
    /** @var PrintLayerToMapLayerConverter */
    protected $sut;

    /** @var ImageManager */
    private $imageManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(PrintLayerToMapLayerConverter::class);
        $this->imageManager = self::$container->get(ImageManager::class);
    }

    public function testConversion(): void
    {
        $printLayer = new PrintLayer(
            true, // is BaseLayer
            $this->createPrintLayerTiles(),
            1,
            'printLayerName',
            'printLayerTitle',
            DemosPlanPath::getTemporaryPath() . '/test-maps/',
        );

        $mapLayer = $this->sut->convert($printLayer);

        $this->assertEquals($printLayer->getLayerTitle(), $mapLayer->getTitle());
        $this->assertEquals($printLayer->getLeft(), $mapLayer->getLeft());
        $this->assertEquals($printLayer->getBottom(), $mapLayer->getBottom());
        $this->assertEquals($printLayer->getRight(), $mapLayer->getRight());
        $this->assertEquals($printLayer->getTop(), $mapLayer->getTop());
    }


    /**
     * @return Collection<int, PrintLayerTile>
     */
    private function createPrintLayerTiles(): Collection
    {
        $gdImage = imagecreate(256, 256);
        imagecolorallocate($gdImage, 0, 0, 0);

        $printLayerTiles1 = new PrintLayerTile(
            "layerTile1",
            DemosPlanPath::getTemporaryPath() . '/test-maps/',
            new PrintLayerTilePosition(1, 1, 1),
            new CoordinatesViewport(123.456, 789.012, 345.678, 901.234),
            256,
            "url-1-1",
            $this->imageManager->make($gdImage)
        );

        $printLayerTiles2 = new PrintLayerTile(
            "layerTile2",
            DemosPlanPath::getTemporaryPath() . '/test-maps/',
            new PrintLayerTilePosition(1, 2, 1),
            new CoordinatesViewport(901.234, 567.890, 123.456, 789.012),
            256,
            "url-1-2",
            $this->imageManager->make($gdImage)
        );

        return new Collection([
            $printLayerTiles1, $printLayerTiles2
        ]);
    }

}
