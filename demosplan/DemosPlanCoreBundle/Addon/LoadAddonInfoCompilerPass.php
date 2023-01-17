<?php

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

        $addonInfos = [];

        foreach ($addons as $name => $manifest) {
            // TODO: maybe we need to register the permission initializers


            $addonInfoDefinition = new Definition(AddonInfo::class);

            $addonInfoDefinition->setShared(false);
            $addonInfoDefinition->setArgument('$name', $name);
            $addonInfoDefinition->setArgument('$manifest', $manifest);

            $slugify = Slugify::create();

            $alias = '@demosplan_addon.'.$slugify->slugify($name);
            $container->setDefinition($alias, $addonInfoDefinition);

            $addonInfos[] = $addonInfoDefinition;
        }

        $container->getDefinition(AddonRegistry::class)->addMethodCall('boot', [$addonInfos]);
    }
}
