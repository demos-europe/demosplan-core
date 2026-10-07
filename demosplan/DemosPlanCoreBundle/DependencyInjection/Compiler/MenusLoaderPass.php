<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use DemosEurope\DemosplanAddon\Utilities\AddonPath;
use demosplan\DemosPlanCoreBundle\Addon\AddonManifestCollection;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\MenusTreeBuilder;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

class MenusLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Installed addons may add entries to the menus with a menus.yml in their config directory.
        $fileLocator = new FileLocator([
            DemosPlanPath::getConfigPath(),
            ...$this->getAddonConfigPaths(),
            DemosPlanPath::getProjectPath('app/Resources/DemosPlanCoreBundle/config'),
        ]);

        $configs = collect($fileLocator->locate('menus.yml', null, false))
            ->map(static fn ($configFile) => Yaml::parseFile($configFile, Yaml::PARSE_CONSTANT))
            ->toArray();

        $configuration = new MenusTreeBuilder();
        $processor = new Processor();
        $merged = $processor->processConfiguration(
            $configuration,
            $configs
        );

        $container->setParameter('menu_definitions', $merged);
    }

    /**
     * @return list<string> the existing config directories of all enabled addons
     */
    private function getAddonConfigPaths(): array
    {
        $configPaths = [];
        foreach (AddonManifestCollection::load() as $addonConfig) {
            if (false === ($addonConfig['enabled'] ?? true)) {
                continue;
            }

            $configPath = AddonPath::getRootPath($addonConfig['install_path'].'/config');
            if (is_dir($configPath)) {
                $configPaths[] = $configPath;
            }
        }

        return $configPaths;
    }
}
