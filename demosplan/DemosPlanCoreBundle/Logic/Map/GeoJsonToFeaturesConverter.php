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

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapProjectionConverter;
use demosplan\DemosPlanCoreBundle\Logic\Maps\WktToGeoJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\UrlFileReader;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\Feature;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayerTile;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\PrintLayerTilePosition;
use Exception;
use Faker\Provider\Uuid;
use geoPHP;
use Intervention\Image\ImageManager;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Tightenco\Collect\Support\Collection;

class GeoJsonToFeaturesConverter
{
    /**
     * @var ImageManager
     */
    private $imageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UrlFileReader
     */
    private $urlFileReader;

    /**
     * @var WktToGeoJsonConverter
     */
    private $wktToGeoJsonConverter;

    public const DEFAULT_TILE_SIZE = 256;

    /**
     * @var MapProjectionConverter
     */
    private $mapProjectionConverter;

    public function __construct(
        ImageManager $imageManager,
        LoggerInterface $logger,
        MapProjectionConverter $mapProjectionConverter,
        UrlFileReader $urlFileReader,
        WktToGeoJsonConverter $wktToGeoJsonConverter
    ) {
        $this->imageManager = $imageManager;
        $this->logger = $logger;
        $this->mapProjectionConverter = $mapProjectionConverter;
        $this->urlFileReader = $urlFileReader;
        $this->wktToGeoJsonConverter = $wktToGeoJsonConverter;
    }

    /**
     * @return Collection<int, Feature>
     *
     * @throws Exception
     */
    public function convert(string $geoJson): Collection
    {
        $geoJson = $this->wktToGeoJsonConverter->convertIfNeeded($geoJson);

        $geoJsonObject = Json::decodeToMatchingType($geoJson);
        $features = $geoJsonObject->features;
        if (null === $features) {
            throw new InvalidArgumentException('Geojson must have a property "features"');
        }
        $result = new Collection();
        foreach ($features as $feature) {
            $geometry = geoPHP::load($feature, 'json');
            $viewport = $this->convertViewport($feature);
            $printLayers = $this->convertPrintLayers($feature);
            $result->add(new Feature($printLayers, $viewport, $geometry));
        }

        return $result;
    }

    private function convertViewport(object $feature): CoordinatesViewport
    {
        $featureLayerExtent = $feature->properties->metadata->featureLayerExtent ?? null;

        return null === $featureLayerExtent
            ? new CoordinatesViewport(0, 0, 0, 0)
            : new CoordinatesViewport(
                $featureLayerExtent[0],
                $featureLayerExtent[1],
                $featureLayerExtent[2],
                $featureLayerExtent[3]
            );
    }

    /**
     * @return Collection<int, PrintLayer>
     */
    private function convertPrintLayers(object $feature): Collection
    {
        $result = new Collection();
        foreach ($feature->properties->metadata->printLayers ?? [] as $printLayer) {
            $imagesDirectoryPath = DemosPlanPath::getTemporaryPath(
                md5($printLayer->layerTitle).'-'.Uuid::uuid().'/'
            );
            $test = new PrintLayer(
                $printLayer->isBaseLayer ?? false,
                $this->convertTiles($printLayer, $imagesDirectoryPath),
                $printLayer->layerMapOrder ?? 0,
                $printLayer->layerName ?? '',
                $printLayer->layerTitle ?? '',
                $imagesDirectoryPath
            );
            $result->add(
                $test
            );
        }

        return $result;
    }

    /**
     * @return Collection<int, PrintLayerTile>
     */
    private function convertTiles(object $printLayer, string $imagesDirectoryPath): Collection
    {
        $result = new Collection();
        foreach ($printLayer->tiles as $tile) {
            $tile = $this->transformTileCoordinates($tile);
            $position = new PrintLayerTilePosition(
                $tile->position->x,
                $tile->position->y,
                $tile->position->z
            );
            $viewport = new CoordinatesViewport(
                $tile->tileExtent[0],
                $tile->tileExtent[1],
                $tile->tileExtent[2],
                $tile->tileExtent[3],
            );
            $imageContent = $this->urlFileReader->getFileContents($tile->url);
            $printLayerTile = new PrintLayerTile(
                $printLayer->layerTitle,
                $imagesDirectoryPath,
                $position,
                $viewport,
                $this->getTileSize($tile),
                $tile->url,
                $this->imageManager->make($imageContent)
            );
            $result->add($printLayerTile);
        }

        return $result;
    }

    /**
     * Tile Coordinates may need to be reprojected as CoordinateViewport
     * only may carry Pseudo Mercator coordinates.
     */
    private function transformTileCoordinates(object $tile): object
    {
        $transformedViewport = $this->mapProjectionConverter->convertCoordinatesViewport(
            new CoordinatesViewport(
                $tile->tileExtent[0],
                $tile->tileExtent[1],
                $tile->tileExtent[2],
                $tile->tileExtent[3],
            ),
            // existing geojson w/o projection may be UTM32N
            $tile->projection ?? MapService::EPSG_25832_PROJECTION_LABEL,
            MapService::PSEUDO_MERCATOR_PROJECTION_LABEL
        );

        $tile->projection = MapService::PSEUDO_MERCATOR_PROJECTION_LABEL;

        $tile->tileExtent = [
            $transformedViewport->getLeft(),
            $transformedViewport->getBottom(),
            $transformedViewport->getRight(),
            $transformedViewport->getTop(),
        ];

        return $tile;
    }

    private function getTileSize(object $tile): int
    {
        return $tile->size ?? self::DEFAULT_TILE_SIZE;
    }
}
