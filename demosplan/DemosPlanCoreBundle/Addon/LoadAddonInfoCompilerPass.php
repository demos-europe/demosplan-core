<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use Cocur\Slugify\Slugify;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class LoadAddonInfoCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $addons = AddonManifestCollection::load();
        $slugify = Slugify::create();

        $addonInfos = [];

        foreach ($addons as $name => $config) {
            $permissionInitializerClass = $config['manifest']['permissionInitializer'];
            $permissionInitializerDefinition = new Definition($permissionInitializerClass);
            $permissionInitializerDefinition->setAutowired(true);
            $permissionInitializerDefinition->setAutoconfigured(true);

            $container->setDefinition($permissionInitializerClass, $permissionInitializerDefinition);

            $addonInfoDefinition = new Definition(AddonInfo::class);

            $addonInfoDefinition->setShared(false);
            $addonInfoDefinition->setArgument('$name', $name);
            $addonInfoDefinition->setArgument('$config', $config);
            $addonInfoDefinition->setArgument('$permissionInitializer', $permissionInitializerDefinition);

            $alias = '@demosplan_addon.'.$slugify->slugify($name);
            $container->setDefinition($alias, $addonInfoDefinition);

            $addonInfos[] = $addonInfoDefinition;
        }

        $container->getDefinition(AddonRegistry::class)->addMethodCall('boot', [$addonInfos]);
    }
}
