<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ManifestConfiguration implements ConfigurationInterface
{
    public const MANIFEST_ROOT = 'demosplan_addon';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('demosplan_addon');
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
