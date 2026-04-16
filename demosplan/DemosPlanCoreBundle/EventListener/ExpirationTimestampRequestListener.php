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

use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\KeycloakTokenRefreshService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenExpirationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\UserFromSecurityUserProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Blocking request listener that checks OAuth token expiration and refreshes tokens if needed.
 *
 * This listener runs on every kernel.controller event (before request processing) to ensure
 * the user has valid OAuth tokens. Uses a session-based threshold to avoid checking on every
 * request - only performs database queries when the threshold has passed.
 *
 * Performance optimization: Stores "next_token_check" timestamp in session. If current time
 * is before this threshold, all token checks are skipped (fast path). Only when threshold
 * is reached, the listener queries database and performs token validation/refresh.
 * The fast-path interval is between 0 and the configured maximum (default 3 minutes), capped
 * at the token's remaining lifetime so we never skip a check past the actual token expiry.
 *
 * Flow:
 * 1. Check session threshold - return early if not reached (fast path, no DB queries)
 * 2. Query database for OAuthToken
 * 3. Check if access token is expired, refresh if possible (KeycloakTokenRefreshService)
 * 4. If refresh token also expired: buffer request, store id_token, redirect to logout (TokenExpirationService)
 * 5. Update session threshold for next check (0 to configured max, capped at token lifetime)
 *
 * CONTEXT: Blocking requests (kernel.controller)
 * USAGE: Ensures valid tokens before request processing
 */
#[AsEventListener(event: 'kernel.controller', priority: 5)]
class ExpirationTimestampRequestListener
{
    public function __construct(
        private readonly KeycloakTokenRefreshService $tokenRefreshService,
        private readonly LoggerInterface $logger,
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly Security $security,
        private readonly TokenExpirationService $tokenExpirationService,
        private readonly UserFromSecurityUserProvider $userFromSecurityUserProvider,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if ($this->shallReturnEarlyAndCheap($event)) {
            return;
        }

        if ($this->shallReturnDueToUserConfig()) {
            return;
        }

        // User and IdP checks are guaranteed by shallReturnDueToUserConfig — safe to load token here.
        // The provider caches its result, so this get() costs no additional DB query.
        $user = $this->userFromSecurityUserProvider->get();
        $oauthToken = $this->oauthTokenRepository->findByUserId($user->getId());
        $session = $event->getRequest()->getSession();

        // null token means no stored credentials — treat as expired and force re-authentication.
        // hasValidTokens is only called when a token exists and takes a non-nullable OAuthToken.
        if (null !== $oauthToken && $this->tokenRefreshService->hasValidTokens($session, $oauthToken)) {
            return;
        }

        $this->tokenExpirationService->handleExpiredTokens($event, $oauthToken);
    }

    private function shallReturnDueToUserConfig(): bool
    {
        // Load the application User entity (one DB query, result cached on the provider).
        // Null means the security user has no matching User in the database (e.g. edge cases).
        // Non-IdP users (form-login) are also skipped — token management only applies to KeyCloak users.
        $user = $this->userFromSecurityUserProvider->get();

        return null === $user || !$user->isProvidedByIdentityProvider();
    }

    /**
     * Cheap early-return checks that run on every single request with zero DB queries.
     *
     * Checks (in order of cost, cheapest first):
     * - Session not started
     * - KeyCloak not configured in production
     * - Login-only mode (oauth_keycloak_login_only parameter — no token refresh needed)
     * - Not a main request (sub-requests skip)
     * - User is a non-human (FunctionalUser — AnonymousUser, AiApiUser, etc.)
     * - Session threshold not yet reached (tokens were recently validated)
     */
    private function shallReturnEarlyAndCheap(ControllerEvent $event): bool
    {
        $session = $event->getRequest()->getSession();

        $securityUser = $this->security->getUser();

        if (!$session->isStarted()
            || $this->ozgKeycloakSessionManager->shouldSkipInProductionWithoutKeycloak()
            || $this->ozgKeycloakSessionManager->isKeycloakLoginOnly()
            || !$event->isMainRequest()
            || null === $securityUser
            || $securityUser instanceof FunctionalUser) {
            return true;
        }

        // Session threshold check - session is user-specific, no user ID needed here
        $nextTokenCheck = $session->get(OzgKeycloakSessionManager::NEXT_TOKEN_CHECK);
        $currentTime = time();
        $thresholdNotReached = null !== $nextTokenCheck && $currentTime < $nextTokenCheck;

        if ($thresholdNotReached) {
            $this->logger->debug('Token check skipped - threshold not reached', [
                'current_time'        => date('Y-m-d H:i:s', $currentTime),
                'next_check'          => date('Y-m-d H:i:s', $nextTokenCheck),
                'seconds_until_check' => $nextTokenCheck - $currentTime,
            ]);
        }

        return $thresholdNotReached;
    }
}
