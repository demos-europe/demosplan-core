<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Factory\GisLayerFactoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerInterface;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;

class GisLayerFactory implements GisLayerFactoryInterface
{
    public function createGisLayer(): GisLayerInterface
    {
        return new GisLayer();
    }
}
