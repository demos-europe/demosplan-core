<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Handles connecting AddOn interfaces with their corresponding CoreEntities.
 */
class AddonResolveTargetEntity implements CompilerPassInterface
{
    private const CORE_ENTITY_DIRECTORY = 'demosplan/DemosPlanCoreBundle/Entity';
    private const ADDON_INTERFACE_DIRECTORY = 'DemosEurope\DemosplanAddon\Contracts\Entities';

    public function process(ContainerBuilder $container): void
    {
        $definition = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
        $corePath = DemosPlanPath::getRootPath(self::CORE_ENTITY_DIRECTORY);

        $iterator = new RecursiveDirectoryIterator($corePath);

        // Go through the files on the Core Entity folder. In case it is a class, detect if any of its interfaces belongs to AddOn middle layer
        // If so, then add it to resolveTargetEntity method call
        foreach (new RecursiveIteratorIterator($iterator) as $filename) {
            $classNameWithoutExtension = pathinfo($filename->getFilename(), \PATHINFO_FILENAME);
            $classNameRaw = $filename->getPath().'/'.$classNameWithoutExtension;

            if (!is_dir($classNameRaw)) {
                $className = str_replace([DemosPlanPath::getRootPath(), '/'], ['', '\\'], $classNameRaw);
                $reflectionClass = new ReflectionClass($className);
                $interfaces = $reflectionClass->getInterfaces();
                foreach ($interfaces as $interface) {
                    if (self::ADDON_INTERFACE_DIRECTORY === $interface->getNamespaceName() && str_contains($interface->getShortName(), $reflectionClass->getShortName())) {
                        $interfaceName = $interface->getNamespaceName().'\\'.$interface->getShortName();
                        $entityName = $reflectionClass->getNamespaceName().'\\'.$reflectionClass->getShortName();
                        $this->addResolveTargetEntityMethodCalls($definition, $interfaceName, $entityName);
                    }
                }
            }
        }
    }

    /**
     * ResolveTargetEntity is a Doctrine utility that enables to map the targetEntity defined in each Addon Entity to an entity defined in Core.
     * ResolveTargetEntity intercepts the Doctrine calls that define the targetEntity, and rewrites that targetEntity (which is an Interface)
     * at runtime with the concrete specified class (which is an entity in Core).
     *
     * @param Definition $definition
     * @param string     $interfaceName
     * @param string     $entityName
     */
    private function addResolveTargetEntityMethodCalls($definition, $interfaceName, $entityName): void
    {
        $definition->addMethodCall('addResolveTargetEntity', [
            $interfaceName, // Resolve from: Interface in AddOn
            $entityName, // Resolve to: Entity in Core
            [],
        ]);
    }
}
