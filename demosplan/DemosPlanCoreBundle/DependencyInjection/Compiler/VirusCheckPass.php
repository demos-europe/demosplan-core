<?php

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use demosplan\DemosPlanCoreBundle\Tools\VirusCheckInterface;
use demosplan\DemosPlanCoreBundle\Tools\VirusCheckHttp;

/**
 * Compiler pass to select the virus check implementation based on environment variable
 */
class VirusCheckPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('avscan_implementation')) {
            return;
        }

        $implementation = $container->getParameter('avscan_implementation');
        $serviceId = 'demosplan\\DemosPlanCoreBundle\\Tools\\' . $implementation;

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
