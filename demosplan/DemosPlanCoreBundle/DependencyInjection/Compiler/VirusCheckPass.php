<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use demosplan\DemosPlanCoreBundle\Tools\VirusCheckHttp;
use demosplan\DemosPlanCoreBundle\Tools\VirusCheckInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass to select the virus check implementation based on environment variable.
 */
class VirusCheckPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('avscan_implementation')) {
            return;
        }

        $implementation = $container->getParameter('avscan_implementation');
        $serviceId = 'demosplan\\DemosPlanCoreBundle\\Tools\\'.$implementation;

        if (!$container->hasDefinition($serviceId)) {
            // Default to HTTP if the requested implementation doesn't exist
            $serviceId = VirusCheckHttp::class;
        }

        // Create an alias from the interface to the implementation
        $container->setAlias(
            VirusCheckInterface::class,
            $serviceId
        )->setPublic(true);
    }
}
