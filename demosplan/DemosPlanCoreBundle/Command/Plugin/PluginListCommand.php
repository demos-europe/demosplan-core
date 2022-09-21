<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Plugin;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanPluginBundle\Logic\PluginList;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * List existing plugins.
 */
class PluginListCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:plugin:list';
    protected static $defaultDescription = 'List all available plugins';
    /**
     * @var PluginList
     */
    private $pluginList;

    public function __construct(ParameterBagInterface $parameterBag, PluginList $pluginList, string $name = null)
    {
        parent::__construct($parameterBag, $name);
        $this->pluginList = $pluginList;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugins = $this->pluginList->getAllPlugins();

        $table = new Table($output);
        $table->setHeaders(
            [
                'enabled',
                'name',
                'description',
                'version',
            ]
        );

        foreach ($plugins as $plugin) {
            $table->addRow(
                [
                    $plugin['enabled'] ? 'x' : '',
                    sprintf('%s', $plugin['name']),
                    sprintf('%s', $plugin['description']),
                    sprintf('%s', $plugin['version']),
                ]
            );
        }

        $table->render();

        return 0;
    }
}
