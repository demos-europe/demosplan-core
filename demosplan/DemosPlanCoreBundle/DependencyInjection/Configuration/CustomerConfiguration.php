<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class CustomerConfiguration implements ConfigurationInterface
{
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
                ->scalarNode('name')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifInArray($this->reservedNames)
                        ->thenInvalid('Customer name is already in use.')
                    ->end()
                ->end()
                ->scalarNode('subdomain')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifInArray($this->reservedSubdomains)
                        ->thenInvalid('Customer subdomain is already in use.')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
