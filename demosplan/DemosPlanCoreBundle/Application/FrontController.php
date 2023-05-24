<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Application;

use demosplan\DemosPlanCoreBundle\Addon\AddonAutoloading;
use demosplan\DemosPlanCoreBundle\Logic\HttpCache;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;

use function set_time_limit;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

/**
 * Centralized front controller entrypoints.
 *
 * This centralizes the web and cli frontcontroller entrypoints
 * to reduce code duplication between all demosplan projects.
 *
 * The general approach is to compare the respective front
 * controller files from a base installation of the current Symfony
 * version and adjust the methods in here accordingly.
 *
 * Hint: These methods are called from their respective expected
 * working directories. Paths lookups should therefore be adjusted
 * accordingly, preferably via `DemosPlanPath::getRootPath()`.
 */
final class FrontController
{
    /**
     * This resembles config/bootstrap.php in a classic Symfony application.
     */
    public static function bootstrap(): void
    {
        // Load cached env vars if the .env.local.php file exists
        // Run "composer dump-env prod" to create it (requires symfony/flex >=1.2)
        $cachedEnvFilename = DemosPlanPath::getRootPath('.env.local.php');
        if (file_exists($cachedEnvFilename) && is_array($env = @include $cachedEnvFilename) && (!isset($env['APP_ENV']) || ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? $env['APP_ENV']) === $env['APP_ENV'])) {
            (new Dotenv())->populate($env);
        } else {
            // load all the .env files
            (new Dotenv())->loadEnv(DemosPlanPath::getRootPath('.env'));
        }

        /* @noinspection AdditionOperationOnArraysInspection */
        $_SERVER += $_ENV;

        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) ?: 'dev';
        $_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? 'prod' !== $_SERVER['APP_ENV'];
        $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = (int) $_SERVER['APP_DEBUG'] || filter_var($_SERVER['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';

        // Add the Addon autoloader to the spl autoload stack
        AddonAutoloading::register();
    }

    /**
     * This is the code typically living in `bin/console` in classic Symfony applications.
     *
     * @throws Exception
     */
    public static function console(string $activeProject, bool $deprecatedFrontcontroller = false): void
    {
        set_time_limit(0);

        $input = new ArgvInput();
        $env = $input->getParameterOption(['--env', '-e'], getenv('SYMFONY_ENV') ?: 'dev', true);
        $debug = '0' !== getenv('SYMFONY_DEBUG') && !$input->hasParameterOption('--no-debug', true) && 'prod' !== $env;

        if ($debug) {
            umask(0000);

            Debug::enable();
        }

        self::bootstrap();

        $kernel = new DemosPlanKernel($activeProject, $env, $debug);
        $application = new ConsoleApplication($kernel, $deprecatedFrontcontroller);

        $application->run($input);
    }

    /**
     * Matches `web/app{_dev}.php.
     *
     * @throws Exception
     */
    public static function web(string $activeProject, bool $debug = false): void
    {
        self::bootstrap();

        $environment = 'prod';
        if ($debug) {
            umask(0000);
            $environment = 'dev';
            Debug::enable();
        }

        /** @var DemosPlanKernel|HttpCache $kernel */
        $kernel = new DemosPlanKernel($activeProject, $environment, $debug);
        $kernel = new HttpCache($kernel);

        // When using the HttpCache, you need to call the
        // method in your front controller instead of relying
        // on the configuration parameter `Request::enableHttpMethodParameterOverride()`;
        $request = Request::createFromGlobals();

        // local and Dataport proxy
        Request::setTrustedProxies(
            ['172.24.116.3', '10.61.16.6'],
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );

        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);
    }
}
