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
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Support\Collection;

/**
 * This is the central information repository about all addons installed on this system and their configuration.
 */
class AddonRegistry
{
    public const ADDON_DIRECTORY = '/addons/';
    public const ADDON_CACHE_DIRECTORY = '/addons/cache/';
    public const ADDON_TYPE = 'dplan-addon';

    private Collection $addons;

    public function __construct()
    {
        $this->loadAddonInformation();
    }

    /**
     * Reads addon information from the configuration file for all installed addons.
     */
    private function loadAddonInformation(): void
    {
        $this->addons = \collect([]);
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
     * returns only enabled addons.
     */
    public function getEnabledAddons(): Collection
    {
        return $this->addons->filter(function ($addon) {
            return $addon['enabled'];
        });
    }

    /**
     * returns only disabled addons.
     */
    public function getDisabledAddons(): Collection
    {
        return $this->addons->filter(function ($addon) {
            return !$addon['enabled'];
        });
    }

    /**
     * Checks if a given composer definition is a correct representation of an addon.
     * If so and if that addon is not yet installed, it will be added to the list of installed addons.
     */
    public function addAddonToRegistry(array $addonComposerDefinition): void
    {
        // only do anything if it is an dplan addon
        $isEntrypointDefined = array_key_exists('entrypoint', $addonComposerDefinition['extra'][self::ADDON_TYPE]);
        $isAddon = self::ADDON_TYPE === $addonComposerDefinition['type'];
        if ($isAddon && $isEntrypointDefined) {
            $entrypoint = $addonComposerDefinition['extra'][self::ADDON_TYPE]['entrypoint'];
            $isAddonInRegistry = $this->addons->has($entrypoint);

            // if addon is not in registry, then add it
            if (!$isAddonInRegistry) {
                $this->addAddon($entrypoint);
            }

            $this->refreshAddonsYaml();
        }
    }

    /**
     * Adds addon to the currently installed pool of addons
     */
    private function addAddon(string $namespacedPathToAddon): void
    {
        $addon = [
            $namespacedPathToAddon => ['enabled' => false],
        ];
        $this->addons->add($addon);
    }

    /**
     * Writes the current collection of addons back into the addons.yaml
     */
    private function refreshAddonsYaml(): void
    {
        $yamlContent = [
            'addons' => $this->addons->collapse()->all(),
        ];
        file_put_contents(DemosPlanPath::getRootPath('addons/addons.yaml'), Yaml::dump($yamlContent, 4));
    }
}
