<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class ApiControllerListener
{
    public function __construct(
        private readonly ApiResourceService $resourceService,
        private readonly RequestStack $requestStack,
        protected readonly ApiLoggerInterface $logger
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $eventController = $event->getController();

        if (!is_array($eventController)) {
            // Closure controller, unhandled
            return;
        }

        if ($eventController[0] instanceof APIController) {
            $apiController = $eventController[0];
            try {
                $apiController->setupApiController($this->requestStack, $this->resourceService);
            } catch (\Exception $e) {
                // log the error. It is not possible to throw an exception here
                // as it would break the application
                $this->logger->warning('API controller setup failed', ['exception' => $e]);
            }
        }

    }
}
