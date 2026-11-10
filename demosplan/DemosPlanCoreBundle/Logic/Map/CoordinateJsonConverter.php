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

use DemosEurope\DemosplanAddon\Utilities\Json;
use InvalidArgumentException;
use JsonException;
use Webmozart\Assert\Assert;

class CoordinateJsonConverter
{
    public function convertCoordinatesToJson(?array $coordinates): string
    {
        return null === $coordinates ? '' : Json::encode($coordinates);
    }

    /**
     * Merges the features of GeoJSON FeatureCollections, e.g. the drawing and the location reference of a statement.
     *
     * Empty values are ignored. If only one value is given it is returned unchanged.
     */
    public function mergeFeatureCollections(string ...$featureCollections): string
    {
        $givenCollections = array_values(array_filter(
            $featureCollections,
            static fn (string $featureCollection): bool => '' !== $featureCollection
        ));
        if (count($givenCollections) <= 1) {
            return $givenCollections[0] ?? '';
        }

        $features = [];
        try {
            foreach ($givenCollections as $featureCollection) {
                $decoded = Json::decodeToArray($featureCollection);
                if (!is_array($decoded['features'] ?? null)) {
                    return $givenCollections[0];
                }
                $features = array_merge($features, $decoded['features']);
            }
        } catch (JsonException) {
            return $givenCollections[0];
        }

        return Json::encode(['type' => 'FeatureCollection', 'features' => $features]);
    }

    public function convertJsonToCoordinates(string $rawCoordinateValues): ?array
    {
        return '' === $rawCoordinateValues ? null : Json::decodeToArray($rawCoordinateValues);
    }

    /**
     * Converts a flat list of coordinates to an array format.
     *
     * @throws InvalidArgumentException if the values are not numeric or the count is incorrect
     */
    public function convertFlatListToCoordinates(string $rawCoordinateValues, bool $isExtendedFormat): ?array
    {
        if ('' === $rawCoordinateValues) {
            return null;
        }

        // Remove square brackets if present
        $rawCoordinateValues = trim($rawCoordinateValues, '[]');
        $rawCoordinateValues = explode(',', $rawCoordinateValues);
        $coordinateValues = [];

        foreach ($rawCoordinateValues as $value) {
            Assert::numeric($value);
            $coordinateValues[] = (float) $value;
        }

        if (!$isExtendedFormat) {
            Assert::count($coordinateValues, 2);

            return [
                'latitude'  => $coordinateValues[0],
                'longitude' => $coordinateValues[1],
            ];
        }

        Assert::count($coordinateValues, 4);

        return [
            'start' => [
                'latitude'  => $coordinateValues[0],
                'longitude' => $coordinateValues[1],
            ],
            'end' => [
                'latitude'  => $coordinateValues[2],
                'longitude' => $coordinateValues[3],
            ],
        ];
    }
}
