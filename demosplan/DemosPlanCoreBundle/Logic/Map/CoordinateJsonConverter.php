<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use DemosEurope\DemosplanAddon\Utilities\Json;
use Webmozart\Assert\Assert;

class CoordinateJsonConverter
{
    public function convertCoordinatesToJson(?array $coordinates): string
    {
        return null === $coordinates ? '' : Json::encode($coordinates, JSON_FORCE_OBJECT);
    }

    public function convertJsonToCoordinates(string $rawCoordinateValues): ?array
    {
        return '' === $rawCoordinateValues ? null : Json::decodeToArray($rawCoordinateValues);
    }

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
