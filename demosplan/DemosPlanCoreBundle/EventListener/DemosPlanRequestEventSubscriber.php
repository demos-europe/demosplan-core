<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiRequestValidator;
use demosplan\DemosPlanCoreBundle\Services\SubdomainHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Custom Eventlistener.
 */
class DemosPlanRequestEventSubscriber implements EventSubscriberInterface
{
    /** @var RouterInterface */
    protected $router;

    /** @var GlobalConfigInterface */
    protected $globalConfig;

    /** @var SubdomainHandlerInterface */
    protected $subdomainHandler;

    public function __construct(
        GlobalConfigInterface $globalConfig,
        private readonly JsonApiRequestValidator $jsonApiRequestValidator,
        RouterInterface $router,
        SubdomainHandlerInterface $subdomainHandler,
    ) {
        $this->subdomainHandler = $subdomainHandler;
        $this->globalConfig = $globalConfig;
        $this->router = $router;
    }

    /**
     * Setze den RequestType in den Request, damit die Applikation je nach dem, ob es ein Master
     * oder Subrequest ist, angepasste Aktionen durchführen kann.
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if (HttpKernelInterface::MAIN_REQUEST === $event->getRequestType()) {
            $event->getRequest()->attributes->set('_request_type', 'master');
        } elseif (HttpKernelInterface::SUB_REQUEST === $event->getRequestType()) {
            $event->getRequest()->attributes->set('_request_type', 'sub');
        }

        $request = $event->getRequest();

        // check whether Platform is in service mode
        if ($this->globalConfig->getPlatformServiceMode()
            && 'core_service_mode' !== $request->attributes->get('_route')) {
            $event->setResponse(
                new RedirectResponse($this->router->generate('core_service_mode'))
            );

            return;
        }

        // API-Requests are always master requests
        if ((HttpKernelInterface::MAIN_REQUEST === $event->getRequestType())
            && $this->jsonApiRequestValidator->isApiRequest($event->getRequest())) {
            $response = $this->jsonApiRequestValidator->validateJsonApiRequest($event->getRequest());
            if ($response instanceof Response) {
                $event->setResponse($response);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }
}
