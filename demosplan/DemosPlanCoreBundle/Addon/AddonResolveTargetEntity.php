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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Handles connecting AddOn interfaces with their corresponding CoreEntities
 */
class AddonResolveTargetEntity implements CompilerPassInterface
{


    public function process(ContainerBuilder $container)
    {

       $definition = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');


        $definition->addMethodCall('addResolveTargetEntity', array(
            ProcedureInterface::class,
            Procedure::class,
            array(),
        ));


        $definition->addMethodCall('addResolveTargetEntity', array(
            StatementInterface::class,
            Statement::class,
            array(),
        ));

        $definition->addMethodCall('addResolveTargetEntity', array(
            UserInterface::class,
            User::class,
            array(),
        ));


        $definition->addMethodCall('addResolveTargetEntity', array(
            EmailAddressInterface::class,
            EmailAddress::class,
            array(),
        ));

        $definition->addMethodCall('addResolveTargetEntity', array(
            FileInterface::class,
            File::class,
            array(),
        ));

        $definition->addTag('doctrine.event_subscriber', array('connection' => 'dplan'));
    }
}
