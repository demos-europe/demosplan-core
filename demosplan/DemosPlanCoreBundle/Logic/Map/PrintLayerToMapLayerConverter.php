<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayerTile;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Symfony\Component\Filesystem\Filesystem;

class PrintLayerToMapLayerConverter
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ImageManager
     */
    private $imageManager;

    public function __construct(Filesystem $filesystem, ImageManager $imageManager)
    {
        $this->filesystem = $filesystem;
        $this->imageManager = $imageManager;
    }

    public function convert(PrintLayer $printLayer): MapLayer
    {
        $mapLayer = new MapLayer(
            $printLayer->getViewport(),
            $this->mergePrintLayerTileImages($printLayer),
            $printLayer->getLayerTitle(),
            $printLayer->getWmsUrl()
        );

        $this->filesystem->remove($printLayer->getImagesDirectoryPath());

        return $mapLayer;
    }

    /**
     * Merges all tile images into a single image. Deletes the tile images from the filesystem
     * as they will not be needed anymore.
     */
    private function mergePrintLayerTileImages(PrintLayer $printLayer): Image
    {
        $placeholderImage = $this->imageManager->canvas(
            $printLayer->getWidthInPixels(),
            $printLayer->getHeightInPixels()
        );

        $tiles = $printLayer->getTilesByRowsAndColumns();
        $col = 0;
        foreach ($tiles as $rows) {
            $placeholderImage = $this->mergeRowTileImages($col, $rows, $placeholderImage);
            ++$col;
        }

        return $placeholderImage;
    }

    /**
     * @param PrintLayerTile[] $columns
     */
    private function mergeRowTileImages(int $col, array $columns, Image $mergeImage): Image
    {
        $row = 0;
        foreach ($columns as $tile) {
            /** @var PrintLayerTile $tile */
            $xPosition = $col * $tile->getWidthInPixels();
            $yPosition = $row * $tile->getWidthInPixels();

            $mergeImage->insert(
                $tile->getImage(),
                'top-left',
                $xPosition,
                $yPosition
            );

            $this->filesystem->remove($tile->getImage()->basePath());
            ++$row;
        }

        return $mergeImage;
    }
}
