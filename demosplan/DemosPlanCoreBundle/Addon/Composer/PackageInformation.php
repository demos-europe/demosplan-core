<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon\Composer;

use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

/**
 * Addons: Composer Information Loader.
 *
 * Load information about addons from their dedicated
 * vendor/ tree and other composer-related files.
 */
final class PackageInformation
{
    public const UNDEFINED_VERSION = '0.0.0.0';

    /**
     * Composer package type for addon packages.
     */
    public const ADDON_COMPOSER_TYPE = 'demosplan-addon';

    private array $addonPackages = [];

    public function __construct()
    {
        $this->reloadPackages();
    }

    public function reloadPackages(): void
    {
        $installedPackagesPath = DemosPlanPath::getRootPath('addons/vendor/composer/installed.php');

        if (!file_exists($installedPackagesPath)) {
            return;
        }

        $packageListPath = include_once $installedPackagesPath;

        if (true === $packageListPath) {
            return;
        }

        if (!array_key_exists('versions', $packageListPath)) {
            return;
        }

        $this->addonPackages = array_filter(
            $packageListPath['versions'],
            static function (array $version): bool {
                return self::ADDON_COMPOSER_TYPE === strtolower($version['type'] ?? 'package');
            }
        );
    }

    public function getInstallPath(string $addonName): string
    {
        if (!$this->hasAddon($addonName)) {
            throw AddonException::missing($addonName);
        }

        return $this->addonPackages[$addonName]['install_path'];
    }

    public function getManifestPath(string $addonName): string
    {
        $path = $this->getInstallPath($addonName).'/demosplan-addon.y*ml';
        $globResult = glob($path);

        if (false === $globResult || 0 === count($globResult)) {
            throw AddonException::invalidManifest($addonName);
        }

        return array_shift($globResult);
    }

    private function hasAddon(string $addonName): bool
    {
        return array_key_exists($addonName, $this->addonPackages);
    }
}
