<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use DemosEurope\DemosplanAddon\Utilities\DemosPlanPath;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\MenusTreeBuilder;

class MenusLoaderPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        $fileLocator = new FileLocator([
            DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Resources/config'),
            DemosPlanPath::getProjectPath('app/Resources/DemosPlanCoreBundle/config'),
        ]);

        $configs = collect($fileLocator->locate('menus.yml', null, false))
            ->map(static function ($configFile) {
                return Yaml::parseFile($configFile, Yaml::PARSE_CONSTANT);
            })
            ->toArray();

        $configuration = new MenusTreeBuilder();
        $processor = new Processor();
        $merged = $processor->processConfiguration(
            $configuration,
            $configs
        );

        $container->setParameter('menu_definitions', $merged);
    }
}
