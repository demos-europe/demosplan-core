<?php

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
use Symfony\Component\DependencyInjection\Dumper\GraphvizDumper;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This Compiler pass dumps an svg of the app container.
 *
 * Following paramters are supported:
 *  `container_dumper_graphivz_enable`: Enables dumping of file
 *  `container_dumper_graphivz_file`: Sets filepath to container dotfile (optional)
 *  `container_dumper_graphivz_dplan_file`: Sets filepath to dplan only dotfile (optional)
 *
 * After Cache clear and calling the project from Test or Browser a new File should
 * be available in /tmp/[projectPrefix]/cache/dev/container.dot
 * This needs to be copied to /srv/www
 * To convert the .dot file to an svg use following command from the host system:
 * `dot -Tsvg container.dot > container.svg` (may take quite a while!)
 *
 * To view the svg a browser may suffice or use a Tool like Inkscape
 *
 * Do not forget to remove the CompilerPass after usage
 *
 * Kudos to https://www.orbitale.io/2018/12/04/the-symfony-container-graph.html
 */
class DumpGraphContainerPass implements CompilerPassInterface
{
    /**
     * @var int Number of lines used to describe meta information in dotfile
     */
    private const DOTFILE_META_LINES_NUMBER = 6;

    public function process(ContainerBuilder $container)
    {
        $parameterEnable = 'container_dumper_graphivz_enable';
        if (!$container->hasParameter($parameterEnable) || !$container->getParameter($parameterEnable)) {
            return;
        }

        // set default paths
        $containerDotfilePath = $container->getParameter('kernel.cache_dir').'/container.dot';
        $containerDplanDotfilePath = $container->getParameter('kernel.cache_dir').'/containerDplan.dot';

        $parameterFileParam = 'container_dumper_graphivz_file';
        $parameterFileDplanParam = 'container_dumper_graphivz_dplan_file';

        if ($container->hasParameter($parameterFileParam)) {
            $containerDotfilePath = $container->getParameter($parameterFileParam);
        }
        if ($container->hasParameter($parameterFileDplanParam)) {
            $containerDplanDotfilePath = $container->getParameter($parameterFileDplanParam);
        }

        // local file only, no need for flysystem
        $fs = new Filesystem();
        if ($fs->exists($containerDotfilePath)) {
            $fs->remove($containerDotfilePath);
        }
        if ($fs->exists($containerDplanDotfilePath)) {
            $fs->remove($containerDplanDotfilePath);
        }

        $containerDotfileContent = (new GraphvizDumper($container))->dump();

        // save container as dotfile
        $fs->appendToFile($containerDotfilePath, $containerDotfileContent);

        // try to include only dplan files in special dotfile
        $content = explode("\n", $containerDotfileContent);
        foreach ($content as $lineNr => $line) {
            // always save first lines, as they contain meta info
            if (self::DOTFILE_META_LINES_NUMBER > $lineNr) {
                $fs->appendToFile($containerDplanDotfilePath, $line."\n");
                continue;
            }
            if (stripos($line, 'demosplan') && false === stripos($line, 'node__service')) {
                $fs->appendToFile($containerDplanDotfilePath, $line."\n");
            }
        }
        // add closing bracket
        $fs->appendToFile($containerDplanDotfilePath, '}');
    }
}
