<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Command\CacheClearCommand;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Check whether apcu cache needs to be cleared.
 */
class ApcuClearListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onControllerRequest(ControllerEvent $event)
    {
        /*
         * $controller passed can be either a class or a Closure.
         * This is not usual in Symfony but it may happen.
         * If it is a class, it comes in array format
         *
         */
        if (!is_array($controllers = $event->getController())) {
            return;
        }

        // key 0 holds controller instance
        $controller = $controllers[0];

        if (!$controller instanceof BaseController) {
            return;
        }

        $cacheScheduleFile = DemosPlanPath::getPublicPath(CacheClearCommand::APCU_CLEAR_SCHEDULE_FILE);

        // uses local file, no need for flysystem
        if (file_exists($cacheScheduleFile)) {
            $this->logger->info('Performing scheduled cache clear for apcu and op caches');

            DemosPlanTools::cacheClear();

            $fs = new DemosFilesystem();
            $fs->remove($cacheScheduleFile);
        }
    }
}
