<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Application;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

class ConsoleApplication extends Application
{
    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);

        /* @var DemosPlanKernel $kernel */
        $this->setName('demosplan.'.$kernel->getActiveProject().' on Symfony');
    }
}
