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
use Exception;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\ErrorHandler\Debug;

class FrontController
{
    /**
     * Build a special console for tests that can be used e.g. to delete caches.
     *
     * @throws Exception
     */
    public static function console()
    {
        set_time_limit(0);

        $input = new ArgvInput();
        $debug = '0' !== getenv('SYMFONY_DEBUG') && !$input->hasParameterOption('--no-debug', true);

        if ($debug) {
            Debug::enable();
        }

        $kernel = new DemosPlanTestKernel(DemosPlanTestKernel::TEST_PROJECT_NAME, 'test', $debug);
        $application = new ConsoleApplication($kernel, false);

        $application->run($input);
    }
}
