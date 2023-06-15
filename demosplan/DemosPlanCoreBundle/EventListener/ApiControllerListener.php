<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class ApiControllerListener
{
    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var ApiResourceService
     */
    private $resourceService;

    public function __construct(RequestStack $requestStack, ApiResourceService $resourceService)
    {
        $this->requestStack = $requestStack;
        $this->resourceService = $resourceService;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $eventController = $event->getController();

        if (!is_array($eventController)) {
            // Closure controller, unhandled
            return;
        }

        if ($eventController[0] instanceof APIController) {
            /** @var APIController $apiController */
            $apiController = $eventController[0];
            $apiController->setupApiController($this->requestStack, $this->resourceService);
        }
    }
}
