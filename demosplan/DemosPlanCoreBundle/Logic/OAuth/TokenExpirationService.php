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

use DateTime;
use DateTimeZone;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;

/**
 * Service for checking OAuth token expiration status.
 *
 * This service contains pure business logic for token expiration checks.
 * Methods accept OAuthToken entities directly for maximum performance -
 * callers can fetch the entity once and perform multiple checks.
 */
class TokenExpirationService
{
    private const TIMEZONE = 'Europe/Berlin';

    /**
     * Check if the access token is currently expired.
     *
     * CONTEXT: Blocking requests (kernel.controller), non-blocking requests (kernel.terminate)
     * USAGE: Check actual expiration without buffer time.
     *
     * @param OAuthToken|null $token The OAuth token entity (null if no token exists)
     *
     * @return bool True if access token is expired or missing, false if still valid
     */
    public function isAccessTokenExpired(?OAuthToken $token): bool
    {
        // No token or no expiration timestamp → expired
        if (null === $token || null === $token->getAccessTokenExpiresAt()) {
            return true;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $now = new DateTime('now', $timezone);

        // Check actual expiration (no buffer)
        return $now >= $token->getAccessTokenExpiresAt();
    }

    /**
     * Check if the refresh token is currently expired.
     *
     * CONTEXT: Blocking requests (kernel.controller), non-blocking requests (kernel.terminate)
     * USAGE: Determines if token refresh is possible or if user must re-authenticate.
     *
     * @param OAuthToken|null $token The OAuth token entity (null if no token exists)
     *
     * @return bool True if refresh token is expired/missing, false if still valid
     */
    public function isRefreshTokenExpired(?OAuthToken $token): bool
    {
        // No token or no refresh token → expired
        if (null === $token || null === $token->getRefreshToken()) {
            return true;
        }

        // No expiration timestamp → invalid state, consider expired
        if (null === $token->getRefreshTokenExpiresAt()) {
            return true;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $now = new DateTime('now', $timezone);

        // Check actual expiration (no buffer)
        return $now >= $token->getRefreshTokenExpiresAt();
    }

    /**
     * Check if the access token needs to be refreshed proactively.
     *
     * CONTEXT: Non-blocking requests (kernel.terminate)
     * USAGE: Background token refresh before expiration occurs.
     *
     * Returns true if:
     * - No token exists (needs authentication)
     * - Token will expire within buffer period (needs proactive refresh)
     * - Token has no expiration (invalid state)
     *
     * Returns false only if token is valid with > bufferMinutes remaining.
     *
     * @param OAuthToken|null $token         The OAuth token entity (null if no token exists)
     * @param int             $bufferMinutes Buffer time in minutes before expiration (default: 2)
     *
     * @return bool True if refresh is needed, false if token is still valid with sufficient time
     */
    public function accessTokenNeedsRefresh(?OAuthToken $token, int $bufferMinutes = 2): bool
    {
        // No token → needs refresh/authentication
        if (null === $token) {
            return true;
        }

        // No expiration timestamp → invalid state, needs refresh
        if (null === $token->getAccessTokenExpiresAt()) {
            return true;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $now = new DateTime('now', $timezone);
        $threshold = (clone $token->getAccessTokenExpiresAt())->modify("-{$bufferMinutes} minutes");

        // Token expires within buffer period → needs refresh
        return $now >= $threshold;
    }
}
