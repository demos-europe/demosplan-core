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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ProxyInstanceSubscriber implements EventSubscriberInterface
{
    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    public function __construct(GlobalConfigInterface $globalConfig)
    {
        $this->globalConfig = $globalConfig;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->isProxyInstance($request)) {
            $redirectUrl = sprintf(
                '%sredirect/?',
                $this->globalConfig->getGatewayRedirectURL(),
            );
            $redirectUrl .= http_build_query(['Token' => $this->sanitizeToken($request->query->get('Token'))]);

            $event->setResponse(new RedirectResponse($redirectUrl));
        }
    }

    /**
     * strip any chars from token that are not allowed.
     */
    private function sanitizeToken(string $token): string
    {
        return preg_replace('/[^a-zA-Z0-9\-]/', '', urldecode($token));
    }

    /**
     * When instance needs to react as a proxy on OSI logins redirect to next system.
     */
    private function isProxyInstance(Request $request): bool
    {
        return '' !== $this->globalConfig->getGatewayRedirectURL() && null !== $request->query->get('Token');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 26]],
        ];
    }
}
