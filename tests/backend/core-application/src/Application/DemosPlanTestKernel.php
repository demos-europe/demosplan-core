<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\CoreApplication\Application;

use DemosEurope\DemosplanAddon\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;

class DemosPlanTestKernel extends DemosPlanKernel
{
    public const TEST_PROJECT_NAME = 'core-application';

    public function __construct(string $activeProject, string $environment, bool $debug)
    {
        parent::__construct($activeProject, $environment, $debug);

        DemosPlanPath::setProjectPathFromConfig('tests/backend/'.self::TEST_PROJECT_NAME);
    }
}
