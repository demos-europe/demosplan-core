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
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;
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
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            echo 'Warning: The console should be invoked via the CLI version of PHP, not the '.PHP_SAPI.' SAPI'.PHP_EOL;
        }

        set_time_limit(0);

        require DemosPlanPath::getRootPath('vendor/autoload.php');

        if (!class_exists(ConsoleApplication::class) || !class_exists(Dotenv::class)) {
            throw new LogicException('You need to add "symfony/framework-bundle" and "symfony/dotenv" as Composer dependencies.');
        }

        $input = new ArgvInput();
        if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
            putenv('APP_ENV='.$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env);
        }

        if ($input->hasParameterOption('--no-debug', true)) {
            putenv('APP_DEBUG='.$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0');
        }

        (new Dotenv())->bootEnv(DemosPlanPath::getRootPath('.env'));

        if ($_SERVER['APP_DEBUG']) {
            umask(0000);

            if (class_exists(Debug::class)) {
                Debug::enable();
            }
        }

        $kernel = new DemosPlanTestKernel(DemosPlanTestKernel::TEST_PROJECT_NAME, 'test', (bool) $_SERVER['APP_DEBUG']);
        $application = new ConsoleApplication($kernel, false);

        $application->run($input);
    }
}
