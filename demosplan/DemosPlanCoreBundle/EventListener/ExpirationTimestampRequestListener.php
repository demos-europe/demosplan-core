<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Logic\User\ExpirationTimestampInjection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Request listener that automatically injects JWT token expiration into session
 * for authenticated users when not already present.
 */
#[AsEventListener(event: 'kernel.request', priority: 5)]
class ExpirationTimestampRequestListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security                     $security,
        private readonly ExpirationTimestampInjection $expirationTimestampInjection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Check if in prod environment
        if (!$this->expirationTimestampInjection->shouldInjectTestExpiration()) {
            return;
        }

        // Only handle main requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Skip if no session is available
        if (!$session->isStarted()) {
            return;
        }

        // Skip if user is not authenticated
        $user = $this->security->getUser();
        if (null === $user) {
            return;
        }

        // Try to get JWT token expiration and store in session
        $this->expirationTimestampInjection->injectTokenExpirationIntoSession($session, $user);
    }
}
