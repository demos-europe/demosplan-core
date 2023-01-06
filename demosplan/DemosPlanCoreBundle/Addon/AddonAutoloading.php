<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

/**
 * Handles the addon autoloading
 */
final class AddonAutoloading
{
    /**
     * Prevent the autoloader from being added multiple times
     */
    private static bool $autoloadingConfigured = false;

    /**
     * Registers an autoload for classes from the addons autoload_classmap.
     */
    public static function register(): void
    {
        if (self::$autoloadingConfigured) {
            return;
        }

        $classMapPath = DemosPlanPath::getRootPath('addons/vendor/composer/autoload_classmap.php');
        if (!file_exists($classMapPath)) {
            return;
        }

        $classmap = include_once $classMapPath;

        spl_autoload_register(static function (string $class) use ($classmap): void {
            if (array_key_exists($class, $classmap)) {
                include_once $classmap[$class];
            }
        });

        self::$autoloadingConfigured = true;
    }
}
