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
use Exception;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Given an array with MapLayer objects returns a new one resulting from merging the
 * image and with the coordinates fitting such image.
 *
 * Class MapLayerMerger
 */
class MapLayerMerger
{
    public function __construct(private readonly Filesystem $fileSystem)
    {
    }

    /**
     * Given an array with MapLayer objects returns a new one resulting from merging the
     * image and with the coordinates fitting such image.
     *
     * @param MapLayer[] $layerImages
     *
     * @throws Exception
     */
    public function merge($layerImages): MapLayer
    {
        if (empty($layerImages)) {
            throw new Exception('No layers received');
        }

        return $this->mergeLayerWithLayersArray(
            array_shift($layerImages),
            $layerImages
        );
    }

    /**
     * Builds a MapLayer by merging $bgLayerImage and the array $mapImageLayers ($bgLayerImage will be the one at the bottom and on top of it the rest of the layers on the array in the order they are there).
     *
     * @param MapLayer[] $layerImages
     */
    private function mergeLayerWithLayersArray(MapLayer $bgLayerImage, array $layerImages): MapLayer
    {
        if (empty($layerImages)) {
            return $bgLayerImage;
        }
        $mergedLayerImage = null;
        $leftOffset = $topOffset = 0;
        $fgLayerImage = array_shift($layerImages);
        // $leftOffset and $topOffset must only be calculated if both layers are WMTS
        if ($bgLayerImage->getCpp() !== $fgLayerImage->getCpp()) {
            [$bgLayerImage, $fgLayerImage] = $this->matchLayersCpp($bgLayerImage, $fgLayerImage);

            $leftOffset = (int) (($fgLayerImage->getLeft() - $bgLayerImage->getLeft()) / $bgLayerImage->getCpp());
            $topOffset = (int) (($bgLayerImage->getTop() - $fgLayerImage->getTop()) / $bgLayerImage->getCpp());
        }

        $mergedLayerImage = $this->mergeLayerImagesWithPositions(
            $bgLayerImage,
            $fgLayerImage,
            $leftOffset,
            $topOffset
        );

        $this->fileSystem->remove($fgLayerImage->getImage()->basePath());
        $this->fileSystem->remove($bgLayerImage->getImage()->basePath());

        if (empty($layerImages)) {
            return $bgLayerImage;
        }

        return $this->mergeLayerWithLayersArray($mergedLayerImage, $layerImages);
    }

    /**
     * @param int $leftOffset
     * @param int $topOffset
     */
    private function mergeLayerImagesWithPositions(
        MapLayer $bgLayerImage,
        MapLayer $fgLayerImage,
        $leftOffset = 0,
        $topOffset = 0
    ): MapLayer {
        $newImage = $bgLayerImage->getImage();
        $newImage->insert(
            $fgLayerImage->getImage(),
            'top-left',
            $leftOffset,
            $topOffset
        );

        $newBgLayerImage = new MapLayer(
            $bgLayerImage->getViewport(),
            $newImage,
            'merged'
        );

        $this->fileSystem->remove($bgLayerImage->getImage()->basePath());

        return $newBgLayerImage;
    }

    /**
     * Returns < 0 if the number of coordinates per pixel in $layerImage1 is less than that
     * in $layerImage2, > 0 if it'sthe other way round, and 0 if they are equal.
     */
    private function cppComparison(MapLayer $layerImage1, MapLayer $layerImage2): int
    {
        return strcmp((string) $layerImage1->getCpp(), (string) $layerImage2->getCpp());
    }

    /**
     * Given two MapLayer objects, returns them ordered by their coordinates per
     * pixel (bigger first, then smaller).
     *
     * @return MapLayer[]
     */
    private function orderLayersByCppSize(MapLayer $layerImage1, MapLayer $layerImage2): array
    {
        if (0 <= $this->cppComparison($layerImage1, $layerImage2)) {
            return [$layerImage1, $layerImage2];
        }

        return [$layerImage2, $layerImage1];
    }

    /**
     * Given two MapLayer objects, checks their coordinates per pixel and modifies the one
     * with the bigger cpp in order for both to have the same coefficient.
     *
     * @return MapLayer[]
     */
    private function matchLayersCpp(MapLayer $layerImage1, MapLayer $layerImage2): array
    {
        [$bigLayerImage, $smallLayerImage] = $this->orderLayersByCppSize(
            $layerImage1,
            $layerImage2
        );

        $newImgWidthPixels = (int) ($bigLayerImage->getWidthInCoordinates() / $smallLayerImage->getCpp());
        $newImgHeightPixels = (int) ($bigLayerImage->getHeightInCoordinates() / $smallLayerImage->getCpp());

        $newResizedImage = $bigLayerImage->getImage()->resize($newImgWidthPixels, $newImgHeightPixels);

        $adaptedLayerImage = new MapLayer(
            $bigLayerImage->getViewport(),
            $newResizedImage,
            $bigLayerImage->getTitle()
        );
        $this->fileSystem->remove($bigLayerImage->getImage()->basePath());

        if ($layerImage1->getTitle() === $adaptedLayerImage->getTitle()) {
            return [$adaptedLayerImage, $smallLayerImage];
        }

        return [$smallLayerImage, $adaptedLayerImage];
    }
}
