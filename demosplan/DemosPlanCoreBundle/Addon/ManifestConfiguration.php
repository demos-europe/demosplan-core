<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ManifestConfiguration implements ConfigurationInterface
{
    final public const MANIFEST_ROOT = 'demosplan_addon';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(self::MANIFEST_ROOT);
        $rootChildren = $tree->getRootNode()->children();

        $rootChildren->scalarNode('humanName')->defaultValue('')->end();
        $rootChildren->scalarNode('vendor')->defaultValue('')->end();
        $rootChildren->scalarNode('description')->defaultValue('')->end();
        $rootChildren->scalarNode('entry')->isRequired()->end();
        $rootChildren->scalarNode('permissionInitializer')->isRequired()->end();
        $rootChildren->arrayNode('controller_paths')
            ->defaultValue([AddonInfo::DEFAULT_CONTROLLER_PATH])
            ->treatNullLike([AddonInfo::DEFAULT_CONTROLLER_PATH])
            ->scalarPrototype()->end()
            ->end();

        $ui = $rootChildren->arrayNode('ui')->children();
        $ui->scalarNode('manifest')->defaultValue('');
        $ui->arrayNode('hooks')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
                ->scalarNode('entry')->end()
                ->arrayNode('options')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ->end();

        return $tree;
    }
}
