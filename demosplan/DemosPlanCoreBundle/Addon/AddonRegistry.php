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

use Carbon\Carbon;
use Composer\Package\PackageInterface;
use demosplan\DemosPlanCoreBundle\Addon\Composer\PackageInformation;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Support\Collection;

/**
 * This is the central information repository about all addons installed on this system and their configuration.
 */
class AddonRegistry
{
    public const ADDON_DIRECTORY = '/addons/';
    public const ADDON_CACHE_DIRECTORY = '/addons/cache/';

    /**
     * Composer package type for addon packages.
     */
    public const ADDON_COMPOSER_TYPE = 'demosplan-addon';

    private const ADDON_YAML_INLINE_DEPTH = 100;

    /**
     * Prevent adding the autoloader multiple times.
     */
    private static bool $autoloadingConfigured = false;

    private Collection $addons;

    private PackageInformation $installedAddons;

    public function __construct()
    {
        $this->installedAddons = new PackageInformation();
        $this->addons = collect();

        $this->loadAddonInformation();
    }

    /**
     * Reads addon information from the configuration file for all installed addons.
     */
    private function loadAddonInformation(): void
    {
        if (file_exists(DemosPlanPath::getRootPath('addons/addons.yaml'))) {
            $configFile = Yaml::parseFile(DemosPlanPath::getRootPath('addons/addons.yaml'));
            if (is_array($configFile) && array_key_exists('addons', $configFile) && is_array($configFile['addons'])) {
                $this->addons = \collect($configFile['addons']);
            }
        }
    }

    /**
     * Returns all available addons.
     */
    public function getAllAddons(): Collection
    {
        return $this->addons;
    }

    /**
     * Checks if a given composer definition is a correct representation of an addon.
     * If so and if that addon is not yet installed, it will be added to the list of installed addons.
     */
    public function register(PackageInterface $addonComposerDefinition): void
    {
        if (self::ADDON_COMPOSER_TYPE !== $addonComposerDefinition->getType()) {
            throw AddonException::invalidType($addonComposerDefinition->getName(), $addonComposerDefinition->getType());
        }

        // if addon is not in registry, then add it
        if (!$this->isRegistered($addonComposerDefinition->getName())) {
            $this->installedAddons->reloadPackages();
            $this->doRegister($addonComposerDefinition);
        }

        $this->refreshAddonsYaml();
    }

    public function get($addonName): array
    {
        return $this->addons[$addonName];
    }

    /**
     * Writes the current collection of addons back into the addons.yaml.
     */
    private function refreshAddonsYaml(): void
    {
        $yamlContent = [
            'addons' => $this->addons->all(),
        ];

        file_put_contents(
            DemosPlanPath::getRootPath('addons/addons.yaml'),
            Yaml::dump($yamlContent, self::ADDON_YAML_INLINE_DEPTH)
        );
    }

    private function isRegistered(string $name): bool
    {
        return $this->addons->has($name);
    }

    private function doRegister(PackageInterface $addonComposerDefinition): void
    {
        $addonName = $addonComposerDefinition->getName();

        $this->addons[$addonName] = [
            'enabled'      => false,
            'installed_at' => Carbon::now()->toIso8601String(),
            'install_path' => realpath($this->installedAddons->getInstallPath($addonName)),
            'manifest'     => $this->loadManifest($addonName),
        ];
    }

    private function loadManifest(string $addonName): array
    {
        try {
            // TODO: Fix manifest parsing
            /*$config = Yaml::parseFile($this->installedAddons->getManifestPath($addonName));
            $processor = new Processor();
            $parsed = $processor->processConfiguration(new ManifestConfiguration(), [$config['demosplan_addon']]);
            var_export($parsed);*/

            return Yaml::parseFile($this->installedAddons->getManifestPath($addonName))[ManifestConfiguration::MANIFEST_ROOT];
        } catch (Exception $e) {
            echo $e->getMessage();
            throw AddonException::invalidManifest($addonName);
        }
    }


    /**
     * Configure autoloading for addons.
     *
     * If possible with the current state of the registry,
     * this will configure autoloading for addon provided classes.
     */
    public function configureAutoloading(): void
    {
        if (self::$autoloadingConfigured) {
            return;
        }

        $classMapPath = DemosPlanPath::getRootPath('addons/vendor/composer/autoload_classmap.php');
        if (!file_exists($classMapPath)) {
            return;
        }

        $classmap = include_once $classMapPath;

        spl_autoload_register(function (string $class) use ($classmap): void {
            if (array_key_exists($class, $classmap)) {
                include $classmap[$class];
            }
        });

        self::$autoloadingConfigured = true;
    }
}
