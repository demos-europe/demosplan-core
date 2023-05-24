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

use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\FormOptionsTreeBuilder;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\ProcedurePhasesTreeBuilder;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

class OptionsLoaderPass implements CompilerPassInterface
{
    public const OVERRIDABLE_CONFIGS = [
        'form_options.yml'      => FormOptionsTreeBuilder::class,
        'procedurephases.yml'   => ProcedurePhasesTreeBuilder::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator([
            DemosPlanPath::getConfigPath(),
            DemosPlanPath::getProjectPath('app/Resources/DemosPlanCoreBundle/config'),
            DemosPlanPath::getConfigPath('procedure'),
            DemosPlanPath::getProjectPath('app/Resources/DemosPlanProcedureBundle/config'),
        ]);

        foreach (self::OVERRIDABLE_CONFIGS as $overridableConfig => $configClassName) {
            $configs = collect($fileLocator->locate($overridableConfig, null, false))
                ->map(static function ($configFile) {
                    return Yaml::parseFile($configFile, Yaml::PARSE_CONSTANT);
                })
                ->toArray();

            $configuration = new $configClassName();
            $processor = new Processor();
            $merged = $processor->processConfiguration(
                $configuration,
                $configs
            );

            $parameterKeys = array_keys($merged);
            foreach ($parameterKeys as $parameterKey) {
                $container->setParameter($parameterKey, $merged[$parameterKey]);
            }
        }
    }
}
