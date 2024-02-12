<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Map;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapOptions;
use TypeError;

class MapOptionsTransformer extends BaseTransformer
{
    /** @var string */
    protected $type = 'MapOptions';

    /**
     * @throws TypeError thrown if null is passed for $mapOptions
     */
    public function transform(MapOptions $mapOptions): array
    {
        return [
            'id'                            => $mapOptions->getId(),
            'defaultMapExtent'              => $mapOptions->getDefaultMaxExtent(),
            'procedureDefaultInitialExtent' => $mapOptions->getProcedureDefaultInitialExtent(),
            'procedureDefaultMaxExtent'     => $mapOptions->getProcedureDefaultMaxExtent(),
            'procedureInitialExtent'        => $mapOptions->getProcedureInitialExtent(),
            'procedureMaxExtent'            => $mapOptions->getProcedureMaxExtent(),
            'globalAvailableScales'         => $mapOptions->getGlobalAvailableScales(),
            'procedureScales'               => $mapOptions->getProcedureScales(),
            'baseLayer'                     => $mapOptions->getBaseLayer(),
            'baseLayerLayers'               => $mapOptions->getBaselayerLayers(),
            'publicSearchAutoZoom'          => $mapOptions->getPublicSearchAutoZoom(),
            'availableProjections'          => $mapOptions->getAvailableProjections(),
            'defaultProjection'             => $mapOptions->getDefaultProjection(),
            'baseLayerProjection'           => $mapOptions->getBaseLayerProjection(),
            'copyright'                     => $mapOptions->getCopyright(),
        ];
    }
}
