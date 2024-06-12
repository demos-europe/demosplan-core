<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Factory\BoilerplateCategoryFactoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;

class BoilerplateCategoryFactory implements BoilerplateCategoryFactoryInterface
{
    public function createBoilerplateCategory(): BoilerplateCategoryInterface
    {
        return new BoilerplateCategory();
    }
}
