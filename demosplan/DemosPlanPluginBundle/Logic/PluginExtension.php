<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanPluginBundle\Logic;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PluginExtension extends Extension
{
    /**
     * @var array List of compiled classes to be added by the plugin
     */
    protected $compiledClasses = [];

    public function load(array $configs, ContainerBuilder $container)
    {
        // needs to be implemented in plugin
        // @see ExamplePlugin
    }
}
