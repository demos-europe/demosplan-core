<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OAuth;

use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Handles OAuth2 token refresh for KeyCloak authentication.
 *
 * This service uses the stored refresh token to obtain new access tokens from KeyCloak,
 * handling token rotation (KeyCloak rotates all tokens on every refresh) and updating
 * the token storage with the new credentials.
 *
 * Concurrent refresh protection via symfony/lock:
 * KeyCloak rotates ALL tokens on every refresh call — the refresh token used in the call
 * is immediately invalidated. Two concurrent processes (e.g. the proactive terminate listener
 * for request N and the blocking controller listener for request N+1) could both read the
 * same refresh token and race to call KeyCloak. The second call would fail with invalid_grant.
 *
 * A per-user lock serializes these attempts. If the lock was contested (another process was
 * refreshing), we do a fresh DB lookup after acquiring to check whether the concurrent refresh
 * succeeded — if it did, we skip our own KeyCloak call. The fresh DB lookup only happens in
 * the race scenario, not on every refresh.
 */
class KeycloakTokenRefreshService
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly OAuthTokenStorageService $tokenStorageService,
        private readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly TokenExpirationService $tokenExpirationService,
    ) {
    }

    /**
     * Checks whether the user has valid OAuth tokens, refreshing if needed. Costs at least one DB query.
     *
     * Returns true if access token is valid or was successfully refreshed.
     * Returns false if both tokens are expired or refresh failed.
     */
    public function hasValidTokens(SessionInterface $session, OAuthToken $oauthToken): bool
    {
        $this->logger->debug('Token check threshold reached - performing validation', [
            'user_id'      => $oauthToken->getUser()->getId(),
            'current_time' => date('Y-m-d H:i:s', time()),
        ]);

        if (!$this->tokenExpirationService->isAccessTokenExpired($oauthToken)) {
            $this->ozgKeycloakSessionManager->syncSession($session, $oauthToken->getUser()->getId(), $oauthToken->getAccessTokenExpiresAt(), $oauthToken->getRefreshTokenExpiresAt());

            return true;
        }

        return $this->tryRefreshTokens($oauthToken);
    }

    /**
     * Check whether a refresh is possible and attempt it.
     *
     * Returns true if the refresh token is still valid and the refresh succeeded.
     * Returns false if the refresh token is expired or the refresh failed.
     *
     * NOTE: On success, the session threshold is updated implicitly via
     * refreshTokensForUser() → OAuthTokenStorageService::storeTokens()
     * → OzgKeycloakSessionManager::syncSession(). No explicit syncSession() call is needed.
     */
    public function tryRefreshTokens(OAuthToken $oauthToken): bool
    {
        $userId = $oauthToken->getUser()->getId();

        $this->logger->info('Access token expired - checking refresh token', ['user_id' => $userId]);

        if ($this->tokenExpirationService->isRefreshTokenExpired($oauthToken)) {
            $this->logger->info('Refresh token also expired', ['user_id' => $userId]);

            return false;
        }

        $refreshSuccess = $this->refreshTokensForUser($userId);

        if (!$refreshSuccess) {
            $this->logger->error('Token refresh failed', ['user_id' => $userId]);

            return false;
        }

        $this->logger->info('Token refresh successful - continuing request', ['user_id' => $userId]);

        return true;
    }

    /**
     * Refresh OAuth tokens for a user using their stored refresh token.
     *
     * IMPORTANT: KeyCloak rotates ALL tokens on refresh (access token, refresh token, ID token).
     * The old refresh token becomes invalid immediately after use.
     *
     * @param string $userId The user ID (UUID)
     *
     * @return bool True if tokens were successfully refreshed, false on any error
     */
    public function refreshTokensForUser(string $userId): bool
    {
        // One lock per user — keyed on user ID so concurrent refreshes for different
        // users never block each other.
        // TTL of 30 seconds is a safety net: if this process crashes without releasing,
        // the lock is automatically freed after 30 seconds by the lock store.
        $lock = $this->lockFactory->createLock('token_refresh_'.$userId, ttl: 30);

        // Track whether we had to wait for the lock — if so, a concurrent process was refreshing
        // and we need a fresh DB lookup after acquiring to avoid a redundant Keycloak call.
        $wasContested = !$lock->acquire(blocking: false);
        if ($wasContested) {
            $lock->acquire(blocking: true);
        }

        try {
            $this->logger->info('Starting token refresh', ['user_id' => $userId]);

            if ($wasContested && $this->oauthTokenRepository->haveTokensBeenRefreshed($userId)) {
                $this->logger->info('Token already refreshed by concurrent process - skipping own KeyCloak call', [
                    'user_id' => $userId,
                ]);

                return true;
            }

            // Get current tokens from storage
            $tokenData = $this->tokenStorageService->getClearTokenData($userId);

            if (null !== $tokenData?->getRefreshToken()) {
                // Get the OAuth2 client
                $client = $this->clientRegistry->getClient('keycloak_ozg');

                $this->logger->debug('Calling KeyCloak to refresh access token', [
                    'user_id'              => $userId,
                    'refresh_token_length' => strlen($tokenData->getRefreshToken()),
                ]);

                // Call KeyCloak to refresh the access token
                // This returns a new AccessToken object with ALL tokens rotated
                $newAccessToken = $client->refreshAccessToken($tokenData->getRefreshToken());

                $this->logger->info('Token refresh successful', [
                    'user_id'                  => $userId,
                    'new_access_token_length'  => strlen($newAccessToken->getToken()),
                    'new_refresh_token_length' => strlen($newAccessToken->getRefreshToken() ?? ''),
                    'expires_at'               => date('Y-m-d H:i:s', $newAccessToken->getExpires()),
                    'seconds_until_expiry'     => $newAccessToken->getExpires() - time(),
                ]);

                // Store the new tokens (all tokens have been rotated by KeyCloak)
                $this->tokenStorageService->storeTokens($userId, $newAccessToken);

                $this->logger->info('New tokens stored successfully', ['user_id' => $userId]);

                return true;
            }

            $this->logger->warning('No tokens or no refresh token available for user', ['user_id' => $userId]);
        } catch (IdentityProviderException $e) {
            // OAuth2-specific errors (invalid token, expired refresh token, network issues, etc.)
            $this->logger->error('OAuth2 provider error during token refresh', [
                'user_id'       => $userId,
                'error'         => $e->getMessage(),
                'response_body' => $e->getResponseBody(),
                'status_code'   => $e->getCode(),
            ]);
        } catch (Exception $e) {
            // General errors (encryption, database, etc.)
            $this->logger->error('Token refresh failed', [
                'user_id'         => $userId,
                'error'           => $e->getMessage(),
                'exception_class' => get_class($e),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
            ]);
        } finally {
            // Always release the lock — even on exception — so the next caller is not blocked.
            $lock->release();
        }

        return false;
    }
}
