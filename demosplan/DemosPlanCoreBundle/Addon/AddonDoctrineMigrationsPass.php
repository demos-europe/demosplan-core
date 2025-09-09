<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Utilities\AddonPath;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddonDoctrineMigrationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $addons = AddonManifestCollection::load();
        // Check if the Doctrine Migrations bundle is registered
        if (!$container->has('doctrine.migrations.configuration')) {
            return;
        }

        // Get the MigrationConfiguration service definition
        $migrationConfigurationDefinition = $container->getDefinition('doctrine.migrations.configuration');

        foreach ($addons as $config) {
            // check if the addon has a DoctrineMigrations directory
            $migrationsPath = AddonPath::getRootPath($config['install_path'].'/src/DoctrineMigrations');
            if (!is_dir($migrationsPath)) {
                continue;
            }
            // fetch the namespace from the mandatory entry point configuration
            $baseNamespace = substr((string) $config['manifest']['entry'], 0, strrpos((string) $config['manifest']['entry'], '\\'));
            // add the migrations directory to the MigrationConfiguration service
            $migrationConfigurationDefinition->addMethodCall('addMigrationsDirectory', [$baseNamespace.'\\DoctrineMigrations', $migrationsPath]);
        }
    }
}
