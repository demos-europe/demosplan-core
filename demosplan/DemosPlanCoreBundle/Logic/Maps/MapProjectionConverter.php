<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Maps;

use DemosEurope\DemosplanAddon\Contracts\Services\MapProjectionConverterInterface;
use DemosEurope\DemosplanAddon\Contracts\ValueObject\CoordinatesViewportInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use Exception;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;
use Psr\Log\LoggerInterface;

class MapProjectionConverter implements MapProjectionConverterInterface
{
    final public const OBJECT_RETURN_TYPE = 'object';
    final public const ARRAY_RETURN_TYPE = 'array';
    final public const STRING_RETURN_TYPE = 'string';

    /**
     * @var Proj4php
     */
    private $projectionTransformer;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->projectionTransformer = new Proj4php();
    }

    /**
     * Transforms the projection for geometries (points, lines, polygons...) inside a string
     * in geojson format.
     *
     * @param string $returnType [self::OBJECT_RETURN_TYPE | self::STRING_RETURN_TYPE]
     */
    public function convertGeoJsonPolygon(
        string $geoJson,
        Proj $currentProjection,
        Proj $newProjection,
        string $returnType = self::OBJECT_RETURN_TYPE,
    ): object|string {
        $geoJson = '' === $geoJson ? [] : Json::decodeToMatchingType($geoJson);
        $result = $geoJson;
        $features = data_get($geoJson, 'features') ?? [];
        $i = 0;
        foreach ($features as $feature) {
            if (null !== $geometry = data_get($feature, 'geometry')) {
                $newCoordinates = match ($geometry->type) {
                    'Polygon' => $this->convertPolygon(
                        $geometry->coordinates,
                        $currentProjection,
                        $newProjection
                    ),
                    'Point' => $this->convertPoint(
                        $geometry->coordinates,
                        $currentProjection,
                        $newProjection
                    ),
                    default => $this->convertLinear(
                        $geometry->coordinates,
                        $currentProjection,
                        $newProjection
                    ),
                };
                $result->features[$i]->geometry->coordinates = $newCoordinates;
            }
            ++$i;
        }

        if (self::OBJECT_RETURN_TYPE !== $returnType) {
            $result = empty($result) ? '' : Json::encode($result);
        }

        return $result;
    }

    /**
     * Transforms the projection for a pair of coordinates defining a rectangle
     * (Ex: '123.456,789.012,345.678,901.234').
     *
     * @param string $returnType [self::ARRAY_RETURN_TYPE | self::STRING_RETURN_TYPE]
     */
    public function convertViewport(
        string $viewport,
        Proj $currentProjection,
        Proj $newProjection,
        string $returnType = self::ARRAY_RETURN_TYPE,
    ): array|string {
        $newViewport = [];
        $viewport = explode(',', $viewport);
        if (is_array($viewport) && 4 === count($viewport)) {
            try {
                $newViewport = $this->convertPoint(
                    array_slice($viewport, 0, 2),
                    $currentProjection,
                    $newProjection
                );
                $newViewport = array_merge(
                    $newViewport,
                    $this->convertPoint(
                        array_slice($viewport, 2, 2),
                        $currentProjection,
                        $newProjection
                    )
                );
            } catch (Exception) {
                $this->logger->warning('Could not convert viewport', [$viewport]);
            }
        }

        return $this->formatResult($newViewport, $returnType);
    }

    public function convertCoordinatesViewport(
        CoordinatesViewportInterface $coordinatesViewport,
        string $sourceProjectionString,
        string $targetProjectionString): CoordinatesViewportInterface
    {
        if ($sourceProjectionString === $targetProjectionString) {
            return $coordinatesViewport;
        }

        $proj4 = new Proj4php();
        $sourceProjection = new Proj($sourceProjectionString, $proj4);
        $targetProjection = new Proj($targetProjectionString, $proj4);
        $sourceLeftPoint = new Point($coordinatesViewport->getLeft(), $coordinatesViewport->getBottom(), $sourceProjection);
        $sourceRightPoint = new Point($coordinatesViewport->getRight(), $coordinatesViewport->getTop(), $sourceProjection);
        $targetLeftPoint = $proj4->transform($targetProjection, $sourceLeftPoint);
        $targetRightPoint = $proj4->transform($targetProjection, $sourceRightPoint);

        return new CoordinatesViewport(
            $targetLeftPoint->toArray()[0],
            $targetLeftPoint->toArray()[1],
            $targetRightPoint->toArray()[0],
            $targetRightPoint->toArray()[1],
        );
    }

    public function convertCoordinate(
        string $coordinate,
        Proj $currentProjection,
        Proj $newProjection,
        string $returnType = self::ARRAY_RETURN_TYPE,
    ) {
        $coordinateArray = explode(',', $coordinate);
        if (is_array($coordinateArray) && 2 === count($coordinateArray)) {
            return $this->convertPoint(
                $coordinateArray,
                $currentProjection,
                $newProjection,
                $returnType);
        }

        return $coordinate;
    }

    /**
     * @param string $returnType [self::ARRAY_RETURN_TYPE | self::STRING_RETURN_TYPE]
     *
     * @return array|string
     */
    private function convertPolygon(
        array $coordinatesGroup,
        Proj $currentProjection,
        Proj $newProjection,
        string $returnType = self::ARRAY_RETURN_TYPE,
    ) {
        $result = [];
        foreach ($coordinatesGroup as $coordinateSubgroup) {
            $result[] = $this->convertLinear(
                $coordinateSubgroup,
                $currentProjection,
                $newProjection
            );
        }

        return $this->formatResult($result, $returnType);
    }

    /**
     * @param string $returnType [self::ARRAY_RETURN_TYPE | self::STRING_RETURN_TYPE]
     *
     * @return array|string
     */
    private function convertLinear(
        array $coordinates,
        Proj $currentProjection,
        Proj $newProjection,
        string $returnType = self::ARRAY_RETURN_TYPE,
    ) {
        $result = [];
        foreach ($coordinates as $coordinate) {
            $result[] = $this->convertPoint(
                $coordinate,
                $currentProjection,
                $newProjection
            );
        }

        return $this->formatResult($result, $returnType);
    }

    /**
     * @param string $returnType [self::ARRAY_RETURN_TYPE | self::STRING_RETURN_TYPE]
     */
    public function convertPoint(
        array $coordinate,
        Proj $currentProjection,
        Proj $newProjection,
        string $returnType = self::ARRAY_RETURN_TYPE,
    ): array|string {
        $pointSrc = new Point($coordinate[0], $coordinate[1], $currentProjection);
        $pointDest = $this
            ->projectionTransformer
            ->transform($newProjection, $pointSrc)
            ->toArray();

        return $this->formatResult([$pointDest[0], $pointDest[1]], $returnType);
    }

    public function getProjection(string $sourceProjectionName): Proj
    {
        return new Proj($sourceProjectionName, new Proj4php());
    }

    /**
     * @param string $type [self::ARRAY_RETURN_TYPE | self::STRING_RETURN_TYPE]
     *
     * @return array|string
     */
    private function formatResult(array $result, string $type)
    {
        return self::ARRAY_RETURN_TYPE === $type ? $result : implode(',', $result);
    }
}
