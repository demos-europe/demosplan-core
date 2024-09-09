<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

/**
 * Handles the addon autoloading.
 */
final class AddonAutoloading
{
    /**
     * Prevent the autoloader from being added multiple times.
     */
    private static bool $autoloadingConfigured = false;

    /**
     * Autoload classes from the addon classmap.
     *
     * We piggyback on composer's authoritative classmap to provide autoloading
     * for the Addons. The general idea is, that demosplan-core's root composer.json
     * defines the outer autoloading boundary, and it's `vendor/autoload.php` is loaded
     * as the very first executed line in all entrypoints. By assuming that all
     * dependencies of demosplan will always be found by these autoloaders, we can
     * safely make a classname lookup here as only actual addon or addon-dependency classes
     * will not have been found up until this point in the autolaoding chain.
     */
    public static function register(): void
    {
        if (self::$autoloadingConfigured) {
            return;
        }

        $classMapPath = DemosPlanPath::getRootPath('addons/vendor/composer/autoload_classmap.php');
        // uses local file, no need for flysystem
        if (!file_exists($classMapPath)) {
            return;
        }

        /** @var array<string, string> $classmap */
        $classmap = include_once $classMapPath;

        spl_autoload_register(static function (string $class) use ($classmap): void {
            if (array_key_exists($class, $classmap)) {
                include_once $classmap[$class];
            }
        });

        self::$autoloadingConfigured = true;
    }
}
