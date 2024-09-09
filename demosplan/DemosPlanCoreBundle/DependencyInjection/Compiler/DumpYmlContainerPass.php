<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;

use function file_put_contents;

/**
 * This Compiler pass dumps the yaml representation of the container.
 *
 * The dump is saved to kernel.logs_dir/container.yml
 */
class DumpYmlContainerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $yamlDumper = new YamlDumper($container);

        $outputPath = $container->getParameter('kernel.logs_dir');
        $outputFilename = $outputPath.'/container.yml';

        // local file is valid, no need for flysystem
        file_put_contents($outputFilename, $yamlDumper->dump());
    }
}
