<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ProcedurePhasesTreeBuilder implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('procedure_phases');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->append($this->getProcedurePhases('internalPhases'))
                ->append($this->getProcedurePhases('externalPhases'))
            ->end();

        return $treeBuilder;
    }

    public function getProcedurePhases(string $name): NodeDefinition
    {
        $treeBuilder = new TreeBuilder($name);

        $node = $treeBuilder->getRootNode()
            ->arrayPrototype()
            ->info('The number of procedure phases can vary depending on the project.')
                ->children()
                    ->scalarNode('name')
                        ->info('The name is the message key.')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('key')
                        ->info('The key for the phase.')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('participationstate')
                        ->info('No idea what that actually is used for.')
                    ->end()
                    ->scalarNode('permissionset')
                        ->info('Specifies basic permission state.')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->booleanNode('previewed')
                        ->info('Shows a preview on start page if set to true.')
                    ->end()
                ->end()
            ->end()
            ->performNoDeepMerging()
            ->normalizeKeys(false);

        return $node;
    }
}
