<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Utilities\DemosPlanPath;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use demosplan\DemosPlanCoreBundle\Command\CacheClearCommand;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;

/**
 * Check whether apcu cache needs to be cleared.
 */
class ApcuClearListener
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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

        $cacheScheduleFile = DemosPlanPath::getProjectPath(CacheClearCommand::APCU_CLEAR_SCHEDULE_FILE);

        if (file_exists($cacheScheduleFile)) {
            $this->logger->info('Performing scheduled cache clear for apcu and op caches');

            DemosPlanTools::cacheClear();

            $fs = new DemosFilesystem();
            $fs->remove($cacheScheduleFile);
        }
    }
}
