<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TrustedProxiesSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GlobalConfigInterface $globalConfig)
    {
    }

    /**
     * Add trusted Proxies to Request.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (0 < (is_countable($this->globalConfig->getProxyTrusted()) ? count($this->globalConfig->getProxyTrusted()) : 0)) {
            $request::setTrustedProxies(
                array_merge($request::getTrustedProxies(), $this->globalConfig->getProxyTrusted()),
                Request::HEADER_X_FORWARDED_ALL
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
