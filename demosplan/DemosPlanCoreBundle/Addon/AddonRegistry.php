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

    /**
     * @return array<string, mixed>
     */
    public function getAddon(string $addonName): array
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

    private function isRegistered(string $addonName): bool
    {
        return $this->addons->has($addonName);
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

    /**
     * @return array<string, array<string, string>>
     */
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
     * @return array<string, array<string, mixed>>>
     */
    public function getFrontendClassesForHook(string $hookName): array
    {
        return $this->addons->map(function (array $item, string $key) use ($hookName) {
            if (!array_key_exists('ui', $item['manifest'])) {
                return [];
            }
            $uiData = $item['manifest']['ui'];

            if (!$item['enabled'] || !array_key_exists($hookName, $uiData['hooks'])) {
                return [];
            }
            $hookData = $uiData['hooks'][$hookName];
            $manifestPath = DemosPlanPath::getRootPath($item['install_path'].'/'.$uiData['manifest']);

            try {
                $entryFile = $this->getAssetPathFromManifest($manifestPath, $hookData['entry']);
                // Try to get the content of the actual asset
                $entryFilePath = DemosPlanPath::getRootPath($item['install_path'].'/'.$entryFile);
                $assetContent = file_get_contents($entryFilePath);
                if (!$assetContent) {
                    return [];
                }
            } catch (AddonException $e) {
                return [];
            }

            return $this->createAddonFrontendAssetsEntry($key, $hookData, $assetContent);
        })->reject(fn (array $value) => [] === $value)->all();
    }

    /**
     * @param array<string, string|array> $hookData
     *
     * @return array<string, array{entry:string, options:array, content:string}>
     */
    private function createAddonFrontendAssetsEntry(string $key, array $hookData, string $assetContent): array
    {
        return [
            $key => [
                'entry'   => $hookData['entry'],
                'options' => $hookData['options'],
                'content' => $assetContent,
            ],
        ];
    }

    /**
     * @throws AddonException
     */
    private function getAssetPathFromManifest(string $manifestPath, string $entryName): string
    {
        if (!file_exists($manifestPath)) {
            AddonException::invalidManifest($manifestPath);
        }

        $manifestContent = Yaml::parseFile($manifestPath);

        if (!array_key_exists($entryName, $manifestContent)) {
            AddonException::manifestEntryNotFound($entryName);
        }

        return $manifestContent[$entryName];
    }
}
