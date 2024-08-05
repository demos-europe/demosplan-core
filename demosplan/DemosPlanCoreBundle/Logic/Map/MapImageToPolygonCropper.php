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
use demosplan\DemosPlanCoreBundle\ValueObject\Map\Feature;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use Symfony\Component\Filesystem\Filesystem;
use Illuminate\Support\Collection;

/**
 * Crops a MapLayer to include a Polygon with a defined minimum margin to the image borders.
 *
 * Class MapImageToPolygonCropper
 */
class MapImageToPolygonCropper
{
    final public const MARGIN_TO_POLYGON = 200;

    /**
     * @var int
     */
    protected $minHeight = 256;

    /**
     * @var int
     */
    protected $minWidth = 256;

    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    private function getPolygonExtentCoordinates(Feature $feature): array
    {
        return $feature->getViewport()->toArray() ?? [0, 0, 0, 0];
    }

    /**
     * Crops a MapLayer to include a Polygon with a defined minimum margin to the image borders.
     *
     * @param Collection<int, Feature> $features
     */
    public function crop(
        MapLayer $layerImage,
        Collection $features
    ): MapLayer {
        $feature = $features->first();

        [$polygonLeft, $polygonBottom, $polygonRight, $polygonTop] = $this
            ->getPolygonExtentCoordinates($feature);

        $cpp = $layerImage->getCpp();
        $mapCoordinateWidth = $layerImage->getWidthInCoordinates();
        $mapCoordinateHeight = $layerImage->getHeightInCoordinates();
        // find out how many coordinates to crop because Tiles overlapped Features
        $marginToPolygon = self::MARGIN_TO_POLYGON * $cpp;
        // Get min width and height in coordinates
        $coordMinWidth = $this->minWidth * $cpp;
        $coordMinHeight = $this->minHeight * $cpp;

        $cropCoordinatesLeft = $cropCoordinatesRight = $cropCoordinatesTop = $cropCoordinatesBottom = 0;
        $newLeft = $layerImage->getLeft();
        $newBottom = $layerImage->getBottom();
        $newRight = $layerImage->getRight();
        $newTop = $layerImage->getTop();
        // Find out how much to crop horizontally and adjust image width accordingly
        if ($mapCoordinateWidth > $coordMinWidth) {
            $cropCoordinatesLeft = $polygonLeft - $layerImage->getLeft() < $marginToPolygon
                ? 0
                : $polygonLeft - $layerImage->getLeft() - $marginToPolygon;

            if ($mapCoordinateWidth - $cropCoordinatesLeft >= $coordMinWidth) {
                $newLeft = $layerImage->getLeft() + $cropCoordinatesLeft;
                // If we can crop and keep width >= min-width we update the width, keep crop size and go for the right border
                $mapCoordinateWidth = $mapCoordinateWidth - $cropCoordinatesLeft;

                $cropCoordinatesRight = $layerImage->getRight() - $polygonRight < $marginToPolygon
                    ? 0
                    : $layerImage->getRight() - $polygonRight - $marginToPolygon;

                if ($mapCoordinateWidth - $cropCoordinatesRight >= $coordMinWidth) {
                    // If we can crop what we need and width >= min-width we update the width
                    $mapCoordinateWidth = $mapCoordinateWidth - $cropCoordinatesRight;
                    $newRight = $layerImage->getRight() - $cropCoordinatesRight;
                } else {
                    $mapCoordinateWidth = $coordMinWidth;
                }
            } else {
                // If when cropping width < min-width we update the crop size to what min-width allows and don't need to treat the right border
                $cropCoordinatesLeft = $mapCoordinateWidth - $coordMinWidth;
                $mapCoordinateWidth = $coordMinWidth;
            }
        }

        // Find out how much to crop vertically and adjust image height accordingly
        if ($mapCoordinateHeight > $coordMinHeight) {
            $cropCoordinatesBottom = $polygonBottom - $layerImage->getBottom() < $marginToPolygon
                ? 0
                : $polygonBottom - $layerImage->getBottom() - $marginToPolygon;

            if ($mapCoordinateHeight - $cropCoordinatesBottom >= $coordMinHeight) {
                $newBottom = $layerImage->getBottom() + $cropCoordinatesBottom;
                // If we can crop and keep height >= min-height we update the height, keep crop size and go for the top border
                $mapCoordinateHeight = $mapCoordinateHeight - $cropCoordinatesBottom;

                $cropCoordinatesTop = $layerImage->getTop() - $polygonTop < $marginToPolygon
                    ? 0
                    : $layerImage->getTop() - $polygonTop - $marginToPolygon;

                if ($mapCoordinateHeight - $cropCoordinatesTop >= $coordMinHeight) {
                    $newTop = $layerImage->getTop() - $cropCoordinatesTop;
                    // If we can crop what we need and height >= min-height we update the height
                    $mapCoordinateHeight = $mapCoordinateHeight - $cropCoordinatesTop;
                } else {
                    // Otherwise we adjust the crop and set height = min-height
                    $cropCoordinatesTop = $mapCoordinateHeight - $coordMinHeight;
                    $mapCoordinateHeight = $coordMinHeight;
                }
            } else {
                // If when cropping height < min-height we update the crop size to what min-height allows and don't need to treat the top border
                // $cropCoordinatesBottom = $mapCoordinateHeight - $coordMinHeight;
                $mapCoordinateHeight = $coordMinHeight;
            }
        }

        // calculate final image dimensions in pixels
        $finalPixelWidth = (int) ($mapCoordinateWidth / $cpp);
        $finalPixelHeight = (int) ($mapCoordinateHeight / $cpp);

        // find top left corner where to set crop rectangle
        $offsetPixelLeft = (int) ($cropCoordinatesLeft / $cpp);
        $offsetPixelTop = (int) ($cropCoordinatesTop / $cpp);

        // finally crop image
        $image = $layerImage->getImage();
        $image->crop($finalPixelWidth, $finalPixelHeight, $offsetPixelLeft, $offsetPixelTop);
        $croppedLayerImage = new MapLayer(
            new CoordinatesViewport(
                $newLeft, $newBottom, $newRight, $newTop
            ),
            $image,
            'cropped'
        );
        $this->filesystem->remove($layerImage->getImage()->basePath());

        return $croppedLayerImage;
    }
}
