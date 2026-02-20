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

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;

/**
 * Handles OAuth2 token refresh for KeyCloak authentication.
 *
 * This service uses the stored refresh token to obtain new access tokens from KeyCloak,
 * handling token rotation (KeyCloak rotates all tokens on every refresh) and updating
 * the token storage with the new credentials.
 */
class KeycloakTokenRefreshService
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly OAuthTokenStorageService $tokenStorageService,
        private readonly TokenExpirationService $tokenExpirationService,
        private readonly LoggerInterface $logger
    ) {
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
        try {
            $this->logger->info('Starting token refresh', ['user_id' => $userId]);

            // Get current tokens from storage
            $tokenData = $this->tokenStorageService->getClearTokenData($userId);

            // Check if tokens and refresh token are available (null-safe: handles missing DB entry)
            if (null === $tokenData?->getRefreshToken()) {
                $this->logger->warning('No tokens or no refresh token available for user', ['user_id' => $userId]);

                return false;
            }

            // Get the OAuth2 client
            $client = $this->clientRegistry->getClient('keycloak_ozg');

            $this->logger->debug('Calling KeyCloak to refresh access token', [
                'user_id' => $userId,
                'refresh_token_length' => strlen($tokenData->getRefreshToken()),
            ]);

            // Call KeyCloak to refresh the access token
            // This returns a new AccessToken object with ALL tokens rotated
            $newAccessToken = $client->refreshAccessToken($tokenData->getRefreshToken());

            $this->logger->info('Token refresh successful', [
                'user_id' => $userId,
                'new_access_token_length' => strlen($newAccessToken->getToken()),
                'new_refresh_token_length' => strlen($newAccessToken->getRefreshToken() ?? ''),
                'expires_at' => date('Y-m-d H:i:s', $newAccessToken->getExpires()),
                'seconds_until_expiry' => $newAccessToken->getExpires() - time(),
            ]);

            // Store the new tokens (all tokens have been rotated by KeyCloak)
            $this->tokenStorageService->storeTokens($userId, $newAccessToken);

            $this->logger->info('New tokens stored successfully', ['user_id' => $userId]);

            return true;
        } catch (IdentityProviderException $e) {
            // OAuth2-specific errors (invalid token, expired refresh token, network issues, etc.)
            $this->logger->error('OAuth2 provider error during token refresh', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'response_body' => $e->getResponseBody(),
                'status_code' => $e->getCode(),
            ]);
        } catch (\Exception $e) {
            // General errors (encryption, database, etc.)
            $this->logger->error('Token refresh failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return false;
    }
}
