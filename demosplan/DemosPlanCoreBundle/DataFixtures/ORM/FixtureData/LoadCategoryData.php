<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\FixtureData;

use demosplan\DemosPlanCoreBundle\Entity\Category;
use Doctrine\Persistence\ObjectManager;

class LoadCategoryData extends \demosplan\DemosPlanCoreBundle\DataFixtures\ORM\CommonData\LoadCategoryData
{
    use FixtureGroup;
}
