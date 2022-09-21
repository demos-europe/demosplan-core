<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits\DI;

use demosplan\DemosPlanCoreBundle\Context\PluginContext;

trait RequiresPluginContextTrait
{
    /**
     * @var PluginContext
     */
    protected $pluginContext;

    /**
     * @return PluginContext
     */
    public function getPluginContext()
    {
        return $this->pluginContext;
    }

    /**
     * @param PluginContext $pluginContext
     */
    public function setPluginContext($pluginContext)
    {
        $this->pluginContext = $pluginContext;
    }
}
