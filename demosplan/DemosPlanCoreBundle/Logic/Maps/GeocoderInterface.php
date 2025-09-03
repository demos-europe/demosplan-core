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

/**
 * Search for addresses and get auto suggestions using the geocoding service provided by BKG / geodatenzentrum.
 */
interface GeocoderInterface
{
    public function searchAddress(string $query, int $limit = 20, ?array $maxExtent = null): array;
}
