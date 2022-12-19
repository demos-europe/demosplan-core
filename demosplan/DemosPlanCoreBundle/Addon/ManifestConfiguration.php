<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ManifestConfiguration implements ConfigurationInterface
{
    public const MANIFEST_ROOT = 'demosplan_addon';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(self::MANIFEST_ROOT);
        $rootChildren = $tree->getRootNode()->children();

        $rootChildren->scalarNode('humanName')->defaultValue('');
        $rootChildren->scalarNode('vendor')->defaultValue('');
        $rootChildren->scalarNode('description')->defaultValue('');
        $rootChildren->scalarNode('entry')->defaultValue('');

        $ui = $rootChildren->arrayNode('ui')->children();
        $ui->scalarNode('manifest')->defaultValue('');
        $ui->arrayNode('hooks')
            ->useAttributeAsKey('name')
            ->scalarPrototype()
            ->end();

        return $tree;
    }
}
