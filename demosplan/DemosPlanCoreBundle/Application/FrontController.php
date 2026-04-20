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
use Closure;
use Exception;
use LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

use function set_time_limit;

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
     * This resembles public/index.php in a classic Symfony application. Should be updated to Runtime Component.
     */
    public static function bootstrap(): void
    {
        (new Dotenv())->bootEnv(DemosPlanPath::getRootPath('.env'));

        // Add the Addon autoloader to the spl autoload stack
        AddonAutoloading::register();
    }

    /**
     * This is the code typically living in `bin/console` in classic Symfony applications.
     *
     * @throws Exception
     */
    public static function bootstrapConsole(): ArgvInput
    {
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            echo 'Warning: The console should be invoked via the CLI version of PHP, not the '.PHP_SAPI.' SAPI'.PHP_EOL;
        }

        set_time_limit(0);

        require DemosPlanPath::getRootPath('vendor/autoload.php');

        if (!class_exists(ConsoleApplication::class) || !class_exists(Dotenv::class)) {
            throw new LogicException('You need to add "symfony/framework-bundle" and "symfony/dotenv" as Composer dependencies.');
        }

        (new Dotenv())->bootEnv(DemosPlanPath::getRootPath('.env'));

        // explicitly set the environment if provided in command
        $input = new ArgvInput();
        if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
            putenv('APP_ENV='.$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env);
        }

        if ($input->hasParameterOption('--no-debug', true)) {
            putenv('APP_DEBUG='.$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0');
        }

        if ($_SERVER['APP_DEBUG']) {
            umask(0000);

            if (class_exists(Debug::class)) {
                Debug::enable();
            }
        }

        return $input;
    }

    /**
     * Runtime-component entry point for `public/index.php`.
     *
     * Symfony runtime handles Dotenv, `Debug::enable()` and trusted-proxy
     * setup; we only need to register addon autoloading and hand back a
     * closure that builds the kernel from the resolved context (APP_ENV,
     * APP_DEBUG, ACTIVE_PROJECT). `HttpCache` wrapping is handled by
     * `framework.http_cache` in prod, not here.
     */
    public static function webRuntime(): Closure
    {
        return static function (array $context): DemosPlanKernel {
            AddonAutoloading::register();

            return new DemosPlanKernel(
                $context['ACTIVE_PROJECT'],
                $context['APP_ENV'],
                (bool) $context['APP_DEBUG'],
            );
        };
    }

    /**
     * Runtime-component entry point for `bin/console` (and `bin/<project>`).
     *
     * Symfony runtime handles Dotenv, `Debug::enable()`, `set_time_limit(0)`
     * and argv parsing of `--env` / `--no-debug`; we only need to register
     * addon autoloading and hand back a closure that wires the kernel into
     * our `ConsoleApplication`.
     */
    public static function consoleRuntime(): Closure
    {
        return static function (array $context): ConsoleApplication {
            AddonAutoloading::register();

            $kernel = new DemosPlanKernel(
                $context['ACTIVE_PROJECT'],
                $context['APP_ENV'],
                (bool) $context['APP_DEBUG'],
            );

            return new ConsoleApplication($kernel, false);
        };
    }

    public static function console(string $activeProject, bool $deprecatedFrontcontroller = false): void
    {
        $input = self::bootstrapConsole();

        // Add the Addon autoloader to the spl autoload stack
        AddonAutoloading::register();

        $kernel = new DemosPlanKernel($activeProject, $_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
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
        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);
    }
}
