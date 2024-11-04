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

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Application\FrontController as BasicFrontController;
use Exception;

class FrontController
{
    /**
     * Build a special console for tests that can be used e.g. to delete caches.
     *
     * @throws Exception
     */
    public static function console()
    {
        $input = BasicFrontController::bootstrapConsole();

        $kernel = new DemosPlanTestKernel(DemosPlanTestKernel::TEST_PROJECT_NAME, 'test', (bool) $_SERVER['APP_DEBUG']);
        $application = new ConsoleApplication($kernel);

        $application->run($input);
    }
}
