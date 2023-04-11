<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use demosplan\DemosPlanCoreBundle\Logic\Maps\MapProjectionConverter;
use demosplan\DemosPlanCoreBundle\Logic\UrlFileReader;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use Exception;
use Intervention\Image\ImageManager;
use Symfony\Component\Filesystem\Filesystem;

class WmsToWmtsCoordinatesConverter
{
    /**
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * @var ImageManager
     */
    private $imageManager;

    /**
     * @var UrlFileReader
     */
    private $urlFileReader;

    /**
     * @var MapProjectionConverter
     */
    private $mapProjectionConverter;

    public function __construct(
        Filesystem $fileSystem,
        ImageManager $imageManager,
        MapProjectionConverter $mapProjectionConverter,
        UrlFileReader $urlFileReader
    ) {
        $this->fileSystem = $fileSystem;
        $this->imageManager = $imageManager;
        $this->mapProjectionConverter = $mapProjectionConverter;
        $this->urlFileReader = $urlFileReader;
    }

    /**
     * Updates all WMS Layers to the coordinates of their contiguous WMTS Layer.
     *
     * @param MapLayer[] $layers
     *
     * @return MapLayer[]
     *
     * @throws Exception
     */
    public function convert(array $layers): array
    {
        if (empty($layers)) {
            throw new Exception('No Layers received');
        }

        return $this->adaptWmsLayerCoordinates(
            array_shift($layers),
            $layers
        );
    }

    /**
     * Updates all WMS Layers to the coordinates of their contiguous WMTS Layer.
     */
    private function adaptWmsLayerCoordinates(MapLayer $bgLayer, array $layers): array
    {
        if (empty($layers)) {
            return [$bgLayer];
        }

        [$successiveWmsLayers, $wmtsLayer, $restOfTheArray] = $this->splitSuccessiveWMSLayers(
            $layers
        );

        if ($bgLayer->isWms()) {
            $wmsLayers = array_merge([$bgLayer], $successiveWmsLayers);
            $adaptedLayers = null === $wmtsLayer
                ? $wmsLayers
                : $this->getWmsLayersWithWmtsLayerCoordinates($wmtsLayer, $wmsLayers);
        } else {
            $adaptedLayers = array_merge(
                [$bgLayer],
                $this->getWmsLayersWithWmtsLayerCoordinates($bgLayer, $successiveWmsLayers)
            );
        }

        return null === $wmtsLayer
            ? $adaptedLayers
            : array_merge(
                $adaptedLayers,
                $this->adaptWmsLayerCoordinates($wmtsLayer, $restOfTheArray)
            );
    }

    /**
     * Given a WmsLayer array returns a new one with the coordinates (and corresponding url
     * and Image) in $wmtsLayer.
     */
    private function getWmsLayersWithWmtsLayerCoordinates(
        MapLayer $wmtsLayer,
        array $wmsLayers
    ): array {
        $newWmsLayerImages = [];
        foreach ($wmsLayers as $wmsLayer) {
            $newWmsLayerImages[] = $this->getSingleWmsLayerWithWmtsLayerCoordinates(
                $wmsLayer,
                $wmtsLayer
            );
        }

        return $newWmsLayerImages;
    }

    /**
     * Given a WmsLayer returns a new one with the coordinates (and corresponding url and
     * Image) in $wmtsLayer.
     */
    private function getSingleWmsLayerWithWmtsLayerCoordinates(
        MapLayer $wmsLayer,
        MapLayer $wmtsLayer
    ): MapLayer {
        $wmsUrl = $this->getWmsUrlWithWmtsLayerCoordinates(
            $wmsLayer->getUrl(),
            $wmtsLayer
        );
        $imageContent = $this->urlFileReader->getFileContents($wmsUrl);
        $newWmsImage = $this->imageManager->make($imageContent);
        $newWmsLayer = new MapLayer(
            new CoordinatesViewport(
                $wmtsLayer->getLeft(),
                $wmtsLayer->getBottom(),
                $wmtsLayer->getRight(),
                $wmtsLayer->getTop(),
            ),
            $newWmsImage,
            '',
            $wmsUrl
        );
        $this->fileSystem->remove($wmsLayer->getImage()->basePath());

        return $newWmsLayer;
    }

    /**
     * Replaces bbox, width and height parameters from $wmsUrl based on $wmtsLayer.
     */
    private function getWmsUrlWithWmtsLayerCoordinates(
        string $wmsUrl,
        MapLayer $wmtsLayer
    ): string {
        $wmsLayerParsedUrl = parse_url($wmsUrl);
        parse_str($wmsLayerParsedUrl['query'], $wmsLayerUrlParameters);
        $wmsLayerUrlParameters['WIDTH'] = $wmtsLayer->getWidthInPixels();
        $wmsLayerUrlParameters['HEIGHT'] = $wmtsLayer->getHeightInPixels();

        $wmsProjection = MapService::PSEUDO_MERCATOR_PROJECTION_LABEL;
        if (array_key_exists('SRS', $wmsLayerUrlParameters)) {
            $wmsProjection = $wmsLayerUrlParameters['SRS'];
        }
        if (array_key_exists('CRS', $wmsLayerUrlParameters)) {
            $wmsProjection = $wmsLayerUrlParameters['CRS'];
        }

        // wms layers may have a different projection than the calculated viewport
        $transformedViewport = $this->mapProjectionConverter->convertCoordinatesViewport(
            new CoordinatesViewport(
                $wmtsLayer->getLeft(),
                $wmtsLayer->getBottom(),
                $wmtsLayer->getRight(),
                $wmtsLayer->getTop(),
            ),
            MapService::PSEUDO_MERCATOR_PROJECTION_LABEL,
            $wmsProjection,
        );

        $wmsNewCoordinates = [
            $transformedViewport->getLeft(),
            $transformedViewport->getBottom(),
            $transformedViewport->getRight(),
            $transformedViewport->getTop(),
        ];

        $wmsLayerUrlParameters['BBOX'] = implode(',', $wmsNewCoordinates);
        $wmsLayerNewParameters = http_build_query($wmsLayerUrlParameters);

        return strtok($wmsUrl, '?').'?'.$wmsLayerNewParameters;
    }

    /**
     * Given an array of MapLayer objects returns:
     *  - An array with all successive WMS Layers at the beginning of the array (empty if none)
     *  - A WMTS Layer right after previous the WMS Layers (null if none).
     *  - The rest of the array after the WMTS Layer (empty if none).
     *
     * @param MapLayer[] $layers
     */
    private function splitSuccessiveWMSLayers(array $layers): array
    {
        if (empty($layers)) {
            return [];
        }
        $successiveWmsLayers = [];
        /** @var MapLayer $nextLayer */
        $nextLayer = array_shift($layers);
        while (null !== $nextLayer && $nextLayer->isWms()) {
            $successiveWmsLayers[] = $nextLayer;
            $nextLayer = array_shift($layers);
        }

        $wmtsLayer = null === $nextLayer || $nextLayer->isWms()
            ? null
            : $nextLayer;

        return [$successiveWmsLayers, $wmtsLayer, $layers];
    }
}
