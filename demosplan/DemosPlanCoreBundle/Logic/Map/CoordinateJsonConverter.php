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

class CoordinateJsonConverter
{
    public function convertCoordinatesToJson(?array $coordinates): string
    {
        return null === $coordinates ? '' : Json::encode($coordinates);
    }

    public function convertJsonToCoordinates(string $rawCoordinateValues): ?array
    {
        return '' === $rawCoordinateValues ? null : Json::decodeToArray($rawCoordinateValues);
    }
}
