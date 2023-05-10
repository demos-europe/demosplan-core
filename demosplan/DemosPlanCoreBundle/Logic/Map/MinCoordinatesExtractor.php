<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;

/**
 * Given an array of Map Layers, returns an array with the left-bottom-right-top coordinates
 * defining the minimal area.
 *
 * Class MinCoordinatesExtractor
 */
class MinCoordinatesExtractor
{
    /**
     * Given an array of MapLayer returns an array with the coordinates (left, bottom,
     * right, top) defining the smallest area.
     *
     * @param MapLayer[] $layerImages
     *
     * @return float[]
     */
    public function extract(array $layerImages): array
    {
        $minLeft = $minBottom = $minRight = $minTop = null;
        foreach ($layerImages as $layerImage) {
            /** @var MapLayer $layerImage */
            if (null === $minLeft || $minLeft < $layerImage->getLeft()) {
                $minLeft = $layerImage->getLeft();
            }
            if (null === $minBottom || $minBottom < $layerImage->getBottom()) {
                $minBottom = $layerImage->getBottom();
            }
            if (null === $minRight || $minRight > $layerImage->getRight()) {
                $minRight = $layerImage->getRight();
            }
            if (null === $minTop || $minTop > $layerImage->getTop()) {
                $minTop = $layerImage->getTop();
            }
        }

        return ['left'=> $minLeft, 'bottom' => $minBottom, 'right' => $minRight, 'top' => $minTop];
    }
}
