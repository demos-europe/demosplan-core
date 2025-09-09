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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class FormOptionsTreeBuilder implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('form_options');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('form_options')
                    ->info('Most paramaters here can have more or less options and are therefore defined as prototypes. The form_options available
                                in a project are the form_options from core modified by the form_options from the project. If keys are present in project
                                they will override (NOT extend) the same keys from core.')
                    ->children()
                        ->arrayNode('statement_fragment_advice_values')
                            ->info('These keys ^ are used with statements as well as fragments. This will work until a) fragment advice values differ from
                                        statement values or b) bobhh activates fragments (because in bobhh the entity names are included in the actual translations).')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('statement_submit_types')
                            ->info('Defines the possible ways to submit a statement and the default option.')
                            ->children()
                                ->arrayNode('values')
                                    ->scalarPrototype()->end()
                                    ->useAttributeAsKey('name')
                                ->end()
                                ->scalarNode('default')->end()
                            ->end()
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('statement_status')
                            ->info('Defines the possible statement states for this project.')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('statement_priority')
                            ->info('Defines the possible statement priorities for this project.')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('project_planning_areas')
                            ->info('Defines the possible planning areas for this project.')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('label')->end()
                                    ->scalarNode('value')->end()
                                ->end()
                            ->end()
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('fragment_status')
                            ->info('Defines the possible fragment status for this project.')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('statement_user_group')
                            ->info('Defines the available user groups for this project.')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('statement_user_position')
                            ->info('Defines the available user positions for this project.')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('statement_user_state')
                            ->info('Defines the available user states for this project.')
                            ->scalarPrototype()->end()
                            ->useAttributeAsKey('name')
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('orga_types')
                        ->info('Available orga types for this project. Or at least how the orga types are presented.')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('name')->end()
                                ->scalarNode('label')->end()
                            ->end()
                        ->end()
                        ->useAttributeAsKey('name')
                        ->performNoDeepMerging()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->normalizeKeys(false);

        return $treeBuilder;
    }
}
