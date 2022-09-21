<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Context;

use demosplan\DemosPlanCoreBundle\Context\IContext\PluginContextInterface;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;

class PluginContext implements PluginContextInterface
{
    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /**
     * @var MessageBagInterface
     */
    protected $messageBag;

    public function __construct(MessageBagInterface $messageBag, GlobalConfigInterface $globalConfig)
    {
        $this->messageBag = $messageBag;
        $this->globalConfig = $globalConfig;
    }

    /**
     * @return MessageBagInterface
     */
    public function getMessageBag()
    {
        return $this->messageBag;
    }

    public function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }
}
