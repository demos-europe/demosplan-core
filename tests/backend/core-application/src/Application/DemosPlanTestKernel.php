<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\CoreApplication\Application;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

class DemosPlanTestKernel extends DemosPlanKernel
{
    public const TEST_PROJECT_NAME = 'core-application';

    public function __construct(string $activeProject, string $environment, bool $debug)
    {
        parent::__construct($activeProject, $environment, $debug);

        DemosPlanPath::setProjectPathFromConfig('tests/backend/'.self::TEST_PROJECT_NAME);
    }
}
