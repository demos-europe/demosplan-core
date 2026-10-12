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

class AddonApiResourceMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('api_platform.resource_class_directories')) {
            return;
        }

        $resourceClassDirectories = $container->getParameter('api_platform.resource_class_directories');

        foreach (AddonManifestCollection::load() as $config) {
            // check if the addon exposes ApiPlatform resources
            $apiPath = AddonPath::getRootPath($config['install_path'].'/src/Api');
            if (!is_dir($apiPath)) {
                continue;
            }

            $resourceClassDirectories[] = $apiPath;
        }

        $container->setParameter('api_platform.resource_class_directories', array_unique($resourceClassDirectories));
    }
}
