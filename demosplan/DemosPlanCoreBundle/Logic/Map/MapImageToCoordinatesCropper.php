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

use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Given a MapLayer and some coordinates defining a rectangle included in the MapLayer,
 * crops to the image to such rectangle.
 *
 * Class MapImageToCoordinatesCropper
 */
class MapImageToCoordinatesCropper
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Crops the received MapLayer to the size and coordinates defined in $minLayerCoordinates.
     *
     * @param float[] $minLayerCoordinates
     */
    public function crop(
        MapLayer $mapLayer,
        array $minLayerCoordinates
    ): MapLayer {
        $coordHeight = $minLayerCoordinates['top'] - $minLayerCoordinates['bottom'];
        $pixelHeight = (int) ($coordHeight / $mapLayer->getCpp());

        $coordWidth = $minLayerCoordinates['right'] - $minLayerCoordinates['left'];
        $pixelWidth = (int) ($coordWidth / $mapLayer->getCpp());

        $coordOffsetLeft = $minLayerCoordinates['left'] - $mapLayer->getLeft();
        $pixelOffsetLeft = (int) ($coordOffsetLeft / $mapLayer->getCpp());

        $coordOffsetTop = $mapLayer->getTop() - $minLayerCoordinates['top'];
        $pixelOffsetTop = (int) ($coordOffsetTop / $mapLayer->getCpp());

        $image = $mapLayer->getImage();
        $image->crop($pixelWidth, $pixelHeight, $pixelOffsetLeft, $pixelOffsetTop);

        $layerImage = new MapLayer(
            new CoordinatesViewport(
                $minLayerCoordinates['left'],
                $minLayerCoordinates['bottom'],
                $minLayerCoordinates['right'],
                $minLayerCoordinates['top'],
            ),
            $image,
            ''
        );
        $this->filesystem->remove($mapLayer->getImage()->basePath());

        return $layerImage;
    }
}
