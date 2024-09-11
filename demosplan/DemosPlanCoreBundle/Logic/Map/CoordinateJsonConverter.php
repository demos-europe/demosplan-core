<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use DemosEurope\DemosplanAddon\Utilities\Json;

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
}
