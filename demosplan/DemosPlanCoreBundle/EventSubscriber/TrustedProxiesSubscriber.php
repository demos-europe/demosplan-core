<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TrustedProxiesSubscriber implements EventSubscriberInterface
{
    /**
     * @var GlobalConfigInterface|GlobalConfig
     */
    private $globalConfig;

    public function __construct(GlobalConfigInterface $globalConfig)
    {
        $this->globalConfig = $globalConfig;
    }

    /**
     * Add trusted Proxies to Request.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (0 < count($this->globalConfig->getProxyTrusted())) {
            $request::setTrustedProxies(
                array_merge($request::getTrustedProxies(), $this->globalConfig->getProxyTrusted()),
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 260],
            ],
        ];
    }
}
