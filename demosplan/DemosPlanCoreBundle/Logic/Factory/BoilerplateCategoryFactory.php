<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Factory\BoilerplateCategoryFactoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;

class BoilerplateCategoryFactory implements BoilerplateCategoryFactoryInterface
{
    public function createBoilerplateCategory() : BoilerplateCategoryInterface
    {
        return new BoilerplateCategory();
    }
}
