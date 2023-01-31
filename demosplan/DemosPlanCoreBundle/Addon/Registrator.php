<?php

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

final class Registrator
{
    public const ADDON_DIRECTORY = '/addons/';
    public const ADDON_CACHE_DIRECTORY = '/addons/cache/';

    private const ADDON_YAML_INLINE_DEPTH = 100;

    private PackageInformation $packageInformation;

    private array $addons;

    public function __construct()
    {
        $this->packageInformation = new PackageInformation();
        $this->addons = AddonManifestCollection::load();
    }

    /**
     * Checks if a given composer definition is a correct representation of an addon.
     * If so and if that addon is not yet installed, it will be added to the list of installed addons.
     */
    public function register(PackageInterface $addonComposerDefinition): string
    {
        if (PackageInformation::ADDON_COMPOSER_TYPE !== $addonComposerDefinition->getType()) {
            throw AddonException::invalidType($addonComposerDefinition->getName(), $addonComposerDefinition->getType());
        }

        // if addon is not in registry, then add it
        if (!$this->isRegistered($addonComposerDefinition->getName())) {
            $this->packageInformation->reloadPackages();
            $this->doRegister($addonComposerDefinition);
        }

        $this->refreshAddonsYaml();

        return $addonComposerDefinition->getName();
    }

    /**
     * Writes the current collection of addons back into the addons.yaml.
     */
    private function refreshAddonsYaml(): void
    {
        $yamlContent = [
            'addons' => $this->addons,
        ];

        $content = "# This file is auto-generated and should not be edited manually unless you know what you're doing.\n";
        $content .= Yaml::dump($yamlContent, self::ADDON_YAML_INLINE_DEPTH);

        file_put_contents(
            DemosPlanPath::getRootPath(AddonManifestCollection::ADDONS_YAML),
            $content
        );
    }

    private function isRegistered(string $addonName): bool
    {
        return array_key_exists($addonName, $this->addons);
    }

    private function doRegister(PackageInterface $addonComposerDefinition): void
    {
        $addonName = $addonComposerDefinition->getName();

        $this->addons[$addonName] = [
            'enabled'      => false,
            'installed_at' => Carbon::now()->toIso8601String(),
            'install_path' => realpath($this->packageInformation->getInstallPath($addonName)),
            'manifest'     => $this->loadManifest($addonName),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function loadManifest(string $addonName): array
    {
        try {
            $config = Yaml::parseFile($this->packageInformation->getManifestPath($addonName))[ManifestConfiguration::MANIFEST_ROOT];

            return (new Processor())->processConfiguration(new ManifestConfiguration(), [$config]);
        } catch (Exception $e) {
            echo $e->getMessage();
            throw AddonException::invalidManifest($addonName);
        }
    }
}
