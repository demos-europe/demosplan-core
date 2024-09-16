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

use demosplan\DemosPlanCoreBundle\ValueObject\Map\Feature;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use Illuminate\Support\Collection;

class FeaturesToMapLayersConverter
{
    public function __construct(private readonly PrintLayerToMapLayerConverter $printLayerToMapLayerConverter)
    {
    }

    /**
     * @param Collection<int, Feature> $features
     *
     * @return Collection<int, MapLayer>
     */
    public function convert(Collection $features): Collection
    {
        $result = new Collection();
        foreach ($features as $feature) {
            $result = $result->merge(
                $this->convertFeatureToMapLayers($feature)
            );
        }

        return $result;
    }

    private function convertFeatureToMapLayers(Feature $feature): Collection
    {
        return $feature->getPrintLayers()->map(
            $this->printLayerToMapLayerConverter->convert(...)
        );
    }
}
