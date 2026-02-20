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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\KeycloakTokenRefreshService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenExpirationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Non-blocking listener that proactively refreshes OAuth tokens approaching expiration.
 *
 * Runs on kernel.terminate (after the response is already sent to the client), so token
 * refresh happens in the background without making the user wait.
 *
 * This listener complements ExpirationTimestampRequestListener:
 * - ExpirationTimestampRequestListener: Blocks requests, handles already-expired tokens
 * - TokenRefreshTerminateListener: Non-blocking, proactively refreshes tokens before expiry
 *
 * The 2-minute refresh buffer means tokens are refreshed before expiry is reached,
 * so the blocking listener rarely needs to handle token expiration at all.
 *
 * After a successful background refresh, the session threshold (oauth_next_token_check)
 * is updated via OzgKeycloakSessionManager::syncSession() so the blocking listener does not
 * wake up prematurely on the next request. Without this update, the threshold would still
 * reflect the old token's remaining lifetime (e.g. 1:50) instead of the new token's full
 * 5-minute lifetime.
 *
 * No session check needed: unlike the blocking listener, this listener never reads from
 * the session itself — CurrentUserInterface uses TokenStorageInterface internally.
 * Session access only happens in syncSession() after a successful refresh, which guards
 * itself with isStarted().
 *
 * Flow:
 * 1. Early-return checks (permission gate, KeyCloak config, main request)
 * 2. Resolve user entity (CurrentUserInterface; non-human users extend FunctionalUser)
 * 3. Load OAuthToken from database
 * 4. Return early if access token is still valid with > 2 minutes remaining
 * 5. Return early if refresh token is expired (blocking listener will redirect to logout)
 * 6. Refresh tokens via KeyCloak in the background (all tokens are rotated on refresh)
 * 7. On success: sync session threshold to reflect the new token's lifetime
 *
 * CONTEXT: Non-blocking (kernel.terminate, after response sent)
 * USAGE: Proactive token refresh to prevent blocking request delays
 */
#[AsEventListener(event: 'kernel.terminate', priority: 0)]
class TokenRefreshTerminateListener
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly TokenExpirationService $tokenExpirationService,
        private readonly KeycloakTokenRefreshService $tokenRefreshService,
        private readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->shallReturnEarly($event)) {
            return;
        }

        $this->refreshIfNeeded();
    }

    /**
     * Cheap early-return checks that run with zero DB queries.
     *
     * Checks (in order of cost, cheapest first):
     * - Permission gate (feature flag for OAuth token management)
     * - KeyCloak not configured in production
     * - Not a main request (sub-requests skip)
     * - User is a non-human (AnonymousUser, AiApiUser, etc. — all extend FunctionalUser)
     */
    private function shallReturnEarly(TerminateEvent $event): bool
    {
        if (!$this->ozgKeycloakSessionManager->hasLogoutWarningPermission()) {
            return true;
        }

        if ($this->ozgKeycloakSessionManager->shouldSkipInProductionWithoutKeycloak()
            || !$event->isMainRequest()) {
            return true;
        }

        return $this->currentUser->getUser() instanceof FunctionalUser;
    }

    /**
     * Check if token needs proactive refresh and attempt it in the background.
     *
     * At this point the user is guaranteed to be a real authenticated User
     * (FunctionalUser was already filtered in shallReturnEarly).
     *
     * Skips if token is still healthy (> 2 minutes remaining) or refresh token
     * has already expired (blocking listener will redirect to logout on next request).
     *
     * On success, syncs the session threshold so the blocking listener does not
     * perform an unnecessary DB query on the immediately following request.
     */
    private function refreshIfNeeded(): void
    {
        $user = $this->currentUser->getUser();
        $oauthToken = $this->oauthTokenRepository->findByUserId($user->getId());

        if (!$this->tokenExpirationService->accessTokenNeedsRefresh($oauthToken)) {
            return;
        }

        if ($this->tokenExpirationService->isRefreshTokenExpired($oauthToken)) {
            $this->logger->info('Proactive refresh skipped - refresh token expired, blocking listener will handle re-authentication', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $this->logger->info('Access token approaching expiry - attempting proactive background refresh', [
            'user_id' => $user->getId(),
        ]);

        $success = $this->tokenRefreshService->refreshTokensForUser($user->getId());

        if ($success) {
            $this->logger->info('Proactive token refresh successful', ['user_id' => $user->getId()]);
        } else {
            $this->logger->warning('Proactive token refresh failed - blocking listener will handle on next request', [
                'user_id' => $user->getId(),
            ]);
        }
    }
}
