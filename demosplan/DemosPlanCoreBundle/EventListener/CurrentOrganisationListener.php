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

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener that initializes the current organisation context for multi-responsibility users.
 *
 * On each request, this listener:
 * 1. Checks if the user is authenticated
 * 2. If yes, initializes the transient currentOrganisation property on the User entity
 *    from the session (via CurrentOrganisationService)
 *
 * This ensures that all calls to User::getOrga() return the session-selected organisation
 * for multi-responsibility users who belong to multiple organisations.
 *
 * Priority 7 ensures this runs after the firewall/security listeners (priority 8)
 * have authenticated the user, but before most application listeners (priority 0).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class CurrentOrganisationListener
{
    public function __construct(
        private readonly Security $security,
        private readonly CurrentOrganisationService $currentOrganisationService,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Only handle main requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip if no session is available
        if (!$request->hasSession() || !$request->getSession()->isStarted()) {
            return;
        }

        // Get the authenticated user
        $user = $this->security->getUser();

        // Only initialize for authenticated User entities
        if (!$user instanceof User) {
            return;
        }

        // Initialize the transient currentOrganisation property from session
        $this->currentOrganisationService->initializeCurrentOrganisation($user);
    }
}
