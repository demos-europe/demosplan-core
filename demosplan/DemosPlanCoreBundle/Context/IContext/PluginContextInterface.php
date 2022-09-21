<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Context\IContext;

use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;

/**
 * Interface PluginContextInterface.
 */
interface PluginContextInterface
{
    /**
     * @return MessageBagInterface
     */
    public function getMessageBag();

    /**
     * @return GlobalConfigInterface
     */
    public function getGlobalConfig();
}
