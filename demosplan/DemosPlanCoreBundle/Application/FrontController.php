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
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Dotenv\Dotenv;

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

    public static function console(): \Closure
    {
        set_time_limit(0);

        self::bootstrap();

        return static function (array $context): ConsoleApplication
        {
            $kernel = new DemosPlanKernel($context['ACTIVE_PROJECT'], $context['APP_ENV'], (bool) $context['APP_DEBUG']);

            // returning an "Application" makes the Runtime run a Console
            // application instead of the HTTP Kernel
            return new ConsoleApplication($kernel);
        };
    }

    public static function web(): \Closure
    {
        self::bootstrap();

        return static function (array $context): DemosPlanKernel {
            return new DemosPlanKernel($context['ACTIVE_PROJECT'], $context['APP_ENV'], (bool) $context['APP_DEBUG']);
        };
    }

    public static function deprecated(): void
    {
        echo '<h1>Front controller has moved</h1><p>Please configure the webserver to call public/index.php as document root</p>';
    }
}
