<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\FixtureData;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\CommonData\LoadRolesData as LoadRolesDataCommon;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Doctrine\Persistence\ObjectManager;

class LoadRolesData extends LoadRolesDataCommon
{
    use FixtureGroup;

}
