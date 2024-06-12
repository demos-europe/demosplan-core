<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Factory\BoilerplateFactoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;

class BoilerplateFactory implements BoilerplateFactoryInterface
{
    public function createBoilerplate(): BoilerplateInterface
    {
        return new Boilerplate();
    }
}
