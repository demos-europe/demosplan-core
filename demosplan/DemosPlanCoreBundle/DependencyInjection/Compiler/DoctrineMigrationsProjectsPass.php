<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineMigrationsProjectsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Check if the Doctrine Migrations bundle is registered
        if (!$container->has('doctrine.migrations.configuration')) {
            return;
        }

        // Get the MigrationConfiguration service definition
        $migrationConfigurationDefinition = $container->getDefinition('doctrine.migrations.configuration');

        // check if the project has a DoctrineMigrations directory
        $migrationsPath = DemosPlanPath::getProjectPath('app/Resources/DemosPlanCoreBundle/DoctrineMigrations');
        if (!is_dir($migrationsPath)) {
            return;
        }
        // add the migrations directory to the MigrationConfiguration service
        $migrationConfigurationDefinition->addMethodCall('addMigrationsDirectory', ['Application\\Migrations\\Project', $migrationsPath]);
    }
}
