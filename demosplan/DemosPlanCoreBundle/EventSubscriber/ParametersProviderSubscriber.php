<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Events\ParameterProviderEventInterface;
use demosplan\DemosPlanCoreBundle\Logic\ViewRenderer;
use Symfony\Component\HttpFoundation\Response;

class ParametersProviderSubscriber extends BaseEventSubscriber
{
    /**
     * @var ViewRenderer
     */
    private $viewRenderer;

    public function __construct(ViewRenderer $viewRenderer)
    {
        $this->viewRenderer = $viewRenderer;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ParameterProviderEventInterface::class => 'processParameters',
        ];
    }

    public function processParameters(ParameterProviderEventInterface $event)
    {
        $view = $event->getView();
        $parameters = $event->getParameters();
        $response = new Response();
        $this->viewRenderer->processRequestStatus();
        $parameters = $this->viewRenderer->processRequestParameters($view, $parameters, $response);
        $event->setParameters($parameters);
    }
}
