<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class FilterNamesConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('filterNames');

        $treeBuilder->getRootNode()
            ->isRequired()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('labelTranslationKey')
            ->isRequired()
            ->end()
            ->scalarNode('comparisonOperator')
            ->isRequired()
            ->end()
            ->scalarNode('rootPath')
            ->isRequired()
            ->end()
            ->arrayNode('grouping')
            ->children()
            ->scalarNode('labelTranslationKey')
            ->isRequired()
            ->end()
            ->scalarNode('targetPath')
            ->isRequired()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
