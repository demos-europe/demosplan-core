<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanPluginBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Context\PluginContext;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresPluginContextTrait;
use demosplan\DemosPlanPluginBundle\Logic\Plugin;

class PluginEventSubscriber extends BaseEventSubscriber
{
    use RequiresPluginContextTrait;
    /**
     * @var Plugin
     */
    protected $plugin;

    public function __construct(PluginContext $pluginContext)
    {
        $this->setPluginContext($pluginContext);
    }

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

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
