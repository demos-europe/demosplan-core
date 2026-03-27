<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security-related response headers that are not covered by NelmioSecurityBundle.
 *
 * - Cache-Control on dynamic responses
 * - Removes X-Powered-By version disclosure
 */
class SecurityHeadersEventSubscriber implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Remove version disclosure headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Set Cache-Control on dynamic responses that don't already have it explicitly set.
        // Static assets are handled by nginx with proper long-lived caching.
        if (!$response->headers->hasCacheControlDirective('public')
            && !$response->headers->hasCacheControlDirective('immutable')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        // Run late to not interfere with other listeners that might set Cache-Control
        return [KernelEvents::RESPONSE => ['onKernelResponse', -128]];
    }
}
