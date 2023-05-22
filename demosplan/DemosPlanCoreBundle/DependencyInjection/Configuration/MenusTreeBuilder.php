<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class MenusTreeBuilder implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('menus');

        $treeBuilder->getRootNode()
            ->append($this->addMenuEntry('sidemenu'))
            ->append($this->addMenuEntry('submenu_templates'))
            ->append($this->addMenuEntry('submenu_procedures'))
        ->end();

        return $treeBuilder;
    }

    private function addMenuEntry(string $name, int $depth = 0): NodeDefinition
    {
        if (2 <= $depth) {
            $treeBuilder = new TreeBuilder($name, 'variable');

            return $treeBuilder->getRootNode();
        }
        $treeBuilder = new TreeBuilder($name);
        $node = $treeBuilder->getRootNode();

        $node->arrayPrototype()
            ->children()
                ->scalarNode('label')
                    ->cannotBeEmpty()
                    ->isRequired()
                ->end()
                ->scalarNode('path')
                    ->isRequired()
                ->end()
                ->arrayNode('child_paths')
                    ->cannotBeEmpty()
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->arrayNode('path_params')
                    ->cannotBeEmpty()
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->variableNode('permission')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('extras')
                    ->info('The key of these will be available in the template. For the value, we check if it is an available parameter. If so, we replace it, otherwise it will stay untoched.')
                    ->cannotBeEmpty()
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->arrayNode('list_item_attributes')
                    ->normalizeKeys(false)
                    ->cannotBeEmpty()
                    ->children()
                        ->variableNode('class')
                        ->end()
                    ->end()
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->arrayNode('link_attributes')
                    ->normalizeKeys(false)
                    ->cannotBeEmpty()
                    ->children()
                        ->variableNode('data-cy')
                        ->end()
                        ->variableNode('data-extern-dataport')
                        ->end()
                    ->end()
                    ->scalarPrototype()
                    ->end()
                ->end()
            ->append($this->addMenuEntry('children', ++$depth))
            ->end()
        ->end();

        return $node;
    }
}
