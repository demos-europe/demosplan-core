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

class CustomerConfiguration implements ConfigurationInterface
{
    final public const NAME = 'customerName';
    final public const SUBDOMAIN = 'customerSubdomain';
    final public const USER_LOGIN = 'userLogin';

    /**
     * @param list<string> $reservedNames
     * @param list<string> $reservedSubdomains
     */
    public function __construct(
        private readonly array $reservedNames,
        private readonly array $reservedSubdomains
    ) {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('customer');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode(self::NAME)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifInArray($this->reservedNames)
                        ->thenInvalid('Customer name is already in use.')
                    ->end()
                ->end()
                ->scalarNode(self::SUBDOMAIN)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifInArray($this->reservedSubdomains)
                        ->thenInvalid('Customer subdomain is already in use.')
                    ->end()
                ->end()
                ->scalarNode(self::USER_LOGIN)
                    ->cannotBeEmpty()
            ->end();

        return $treeBuilder;
    }
}
