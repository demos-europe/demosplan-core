<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanPluginBundle\EventListener;

use demosplan\DemosPlanCoreBundle\EventListener\BaseEventListener;
use demosplan\DemosPlanPluginBundle\Logic\Plugin;

class PluginEventListener extends BaseEventListener
{
    /**
     * @var Plugin
     */
    protected $plugin;

    /**
     * @return Plugin
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * @param Plugin $plugin
     */
    public function setPlugin($plugin)
    {
        $this->plugin = $plugin;
    }
}
