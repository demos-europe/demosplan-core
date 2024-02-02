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


use DemosEurope\DemosplanAddon\Contracts\Entities\EmailAddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Handles connecting AddOn interfaces with their corresponding CoreEntities
 */
class AddonResolveTargetEntity implements CompilerPassInterface
{

    private const CORE_ENTITY_DIRECTORY = 'demosplan/DemosPlanCoreBundle/Entity';
    private const ADDON_INTERFACE_DIRECTORY = 'DemosEurope\DemosplanAddon\Contracts\Entities';


    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
        $corePath = DemosPlanPath::getRootPath(self::CORE_ENTITY_DIRECTORY);


        $iterator = new RecursiveDirectoryIterator($corePath);

        foreach (new RecursiveIteratorIterator($iterator) as  $filename) {
            $classNameWithoutExtension = pathinfo($filename->getFilename(), \PATHINFO_FILENAME);
            $classNameRaw = $filename->getPath() . '/' . $classNameWithoutExtension;

            if (!is_dir($classNameRaw)) {
                $className = str_replace(array(DemosPlanPath::getRootPath(), '/'), array('', '\\'), $classNameRaw);
                $reflectionClass = new \ReflectionClass($className);
                $interfaces = $reflectionClass->getInterfaces();
                foreach ($interfaces as $interface) {

                    if ($interface->getNamespaceName() === self::ADDON_INTERFACE_DIRECTORY && str_contains($interface->getShortName(), $reflectionClass->getShortName())) {
                        $interfaceName = $interface->getNamespaceName() . '\\' . $interface->getShortName();
                        $entityName = $reflectionClass->getNamespaceName() . '\\' . $reflectionClass->getShortName();
                        $this->addResolveTargetEntityMethodCalls($definition, $interfaceName, $entityName);

                    }
                }
            }
        }

        $definition->addTag('doctrine.event_subscriber', array('connection' => 'dplan'));
    }

    private function addResolveTargetEntityMethodCalls($definition, $interface, $entity)
    {
        $definition->addMethodCall('addResolveTargetEntity', array(
            $interface,
            $entity,
            array(),
        ));
    }
}
