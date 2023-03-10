<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerInterface;
use DemosEurope\DemosplanAddon\Contracts\Factory\GisLayerFactoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;

class GisLayerFactory implements GisLayerFactoryInterface
{
    public function createGisLayer(): GisLayerInterface
    {
        return new GisLayer();
    }
}
