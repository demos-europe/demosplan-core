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

use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakLogoutManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Request listener that automatically injects expiration timestamp into session
 * for authenticated users when not already present.
 */
#[AsEventListener(event: 'kernel.controller', priority: 5)]
class ExpirationTimestampRequestListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OzgKeycloakLogoutManager $ozgKeycloakLogoutManager,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 5],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->ozgKeycloakLogoutManager->hasLogoutWarningPermission() &&
            !$this->ozgKeycloakLogoutManager->isKeycloakConfigured()) {
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

        // Check if in test or dev environment
        if ($this->ozgKeycloakLogoutManager->shouldInjectTestExpiration()) {
            $this->ozgKeycloakLogoutManager->injectTokenExpirationIntoSession($session, $user);
        }

        if ($this->ozgKeycloakLogoutManager->hasValidToken($session)) {
            return;
        }

        $this->handleExpiredToken($event);
    }

    private function handleExpiredToken(ControllerEvent $event): void
    {
        $this->logger->info('Token expired, redirecting to logout');

        $redirectResponse = new RedirectResponse($this->router->generate('DemosPlan_user_logout'));
        $event->setController(static fn () => $redirectResponse);
    }
}
