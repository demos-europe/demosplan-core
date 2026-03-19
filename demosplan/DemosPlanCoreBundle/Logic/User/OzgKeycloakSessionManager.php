<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DateTime;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Manages the intersection of KeyCloak OAuth2 state and PHP session state.
 *
 * Responsibilities:
 * - KeyCloak configuration and environment guards
 * - Permission checks for OAuth token management features
 * - Session setup after successful token validation or refresh (syncSession)
 * - id_token storage in session for KeyCloak logout URL construction
 * - Building KeyCloak logout URLs with customer subdomain and id_token hint
 */
class OzgKeycloakSessionManager
{
    public const EXPIRATION_TIMESTAMP = 'expirationTimestamp';
    public const KEYCLOAK_TOKEN = 'keycloakToken';

    /** Session key for storing the next blocking token-check threshold */
    public const NEXT_TOKEN_CHECK = 'oauth_next_token_check';

    private const POST_LOGOUT_REDIRECT_URI = 'post_logout_redirect_uri=https://';
    private const ID_TOKEN_HINT = 'id_token_hint=';

    /** Default session expiration time when not set in parameters (6 hours) */
    private const DEFAULT_SESSION_LIFETIME_SECONDS = 21600;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
        private readonly CurrentUserService $currentUser,
        private readonly CustomerService $customerService,
        private readonly ParameterBagInterface $parameterBag,
        private readonly CustomerOAuthConfigRepository $configRepository,
    ) {
    }

    /**
     * Check if Keycloak logout is configured for the current customer.
     * Per-customer config takes precedence over the global parameter.
     */
    public function isKeycloakConfigured(): bool
    {
        return '' !== ($this->getEffectiveLogoutRoute() ?? '');
    }

    /**
     * Returns the effective logout route for the current customer.
     * Per-customer config takes precedence; falls back to the global parameter.
     */
    public function getEffectiveLogoutRoute(): ?string
    {
        $customer = $this->customerService->getCurrentCustomer();
        $config = $this->configRepository->findByCustomer($customer);

        $perCustomerRoute = $config?->getKeycloakLogoutRoute();
        if (null !== $perCustomerRoute && '' !== $perCustomerRoute) {
            return $perCustomerRoute;
        }

        $globalRoute = $this->parameterBag->get('oauth_keycloak_logout_route');

        return '' !== $globalRoute ? $globalRoute : null;
    }

    public function shouldSkipInProductionWithoutKeycloak(): bool
    {
        return DemosPlanKernel::ENVIRONMENT_PROD === $this->kernel->getEnvironment()
            && !$this->isKeycloakConfigured();
    }

    public function isKeycloakLoginOnly(): bool
    {
        return $this->currentUser->hasPermission('feature_keycloak_used_for_login_only');
    }

    /**
     * Stores expiration timestamp into the user session.
     */
    public function injectTokenExpirationIntoSession(SessionInterface $session, string $userId): void
    {
        // Skip if expiration is already present in session
        if ($session->has(self::EXPIRATION_TIMESTAMP)) {
            return;
        }

        try {
            $metadataBag = $session->getMetadataBag();
            $sessionCreated = $metadataBag->getCreated();
            $sessionLifetime = $this->parameterBag->get('session_lifetime_seconds') ?: self::DEFAULT_SESSION_LIFETIME_SECONDS;
            $expirationTimestamp = $sessionCreated + $sessionLifetime;

            $session->set(self::EXPIRATION_TIMESTAMP, $expirationTimestamp);

            $this->logger->debug('Expiration timestamp injected into session', [
                'user_id'    => $userId,
                'expiration' => $expirationTimestamp,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to inject expiration timestamp into session', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store ID token in session for KeyCloak logout.
     *
     * The ID token is needed when redirecting to KeyCloak logout endpoint
     * to perform silent logout without user confirmation dialog.
     */
    public function storeIdTokenForLogout(SessionInterface $session, string $idToken): void
    {
        $session->set(self::KEYCLOAK_TOKEN, $idToken);
        $this->logger->info('Storing keycloak id_token in session for logout');
    }

    public function getLogoutUrl(string $logoutRoute, ?string $keycloakToken): string
    {
        $currentCustomer = $this->customerService->getCurrentCustomer();

        $logoutRoute = str_replace(
            self::POST_LOGOUT_REDIRECT_URI,
            self::POST_LOGOUT_REDIRECT_URI.$currentCustomer->getSubdomain().'.',
            $logoutRoute
        );

        if ($keycloakToken) {
            $logoutRoute = str_replace(
                self::ID_TOKEN_HINT,
                self::ID_TOKEN_HINT.$keycloakToken,
                $logoutRoute
            );
        }

        return $logoutRoute;
    }

    /**
     * Sync PHP session state after successful token validation or refresh.
     *
     * Sets the fast-path threshold to skip token checks for the next interval (default 3 minutes),
     * capped at the access token's remaining lifetime so we never skip a check past actual expiry.
     *
     * Also updates EXPIRATION_TIMESTAMP (used by the FE logout countdown) to the refresh token expiry
     * when full KeyCloak token management is active. The refresh token TTL is the real user-facing
     * session boundary — the access token is an implementation detail refreshed transparently.
     * When feature_keycloak_used_for_login_only is enabled, EXPIRATION_TIMESTAMP is left untouched
     * (already set to the PHP session lifetime by injectTokenExpirationIntoSession on login).
     *
     * Used after:
     * - Successful blocking token validation or refresh (ExpirationTimestampRequestListener)
     * - Successful background token refresh (OAuthTokenStorageService via storeTokens)
     *
     * @param SessionInterface $session               The current user session
     * @param string           $userId                User ID for logging
     * @param DateTime|null    $accessTokenExpiresAt  Access token expiration (used for fast-path threshold)
     * @param DateTime|null    $refreshTokenExpiresAt Refresh token expiration (used for FE countdown)
     */
    public function syncSession(
        SessionInterface $session,
        string $userId,
        ?DateTime $accessTokenExpiresAt,
        ?DateTime $refreshTokenExpiresAt = null,
    ): void {
        $checkInterval = $this->parameterBag->get('oauth_token_fast_path_interval_seconds');

        if (null !== $accessTokenExpiresAt) {
            $secondsUntilExpiry = $accessTokenExpiresAt->getTimestamp() - time();
            // min/max guards against negative values if token is already expired
            $checkInterval = min($checkInterval, max(0, $secondsUntilExpiry));
        }

        $nextCheck = time() + $checkInterval;
        $session->set(self::NEXT_TOKEN_CHECK, $nextCheck);

        $this->logger->debug('Session token check threshold updated', [
            'user_id'          => $userId,
            'next_check'       => date('Y-m-d H:i:s', $nextCheck),
            'interval_seconds' => $checkInterval,
        ]);

        // In login-only mode the PHP session lifetime governs EXPIRATION_TIMESTAMP — leave it untouched.
        if ($this->isKeycloakLoginOnly()) {
            return;
        }

        // Use refresh token expiry for the FE countdown — it reflects the real session boundary.
        // Fall back to access token expiry if no refresh token is available.
        $expirationTimestamp = $refreshTokenExpiresAt ?? $accessTokenExpiresAt;

        if (null === $expirationTimestamp) {
            return;
        }

        $session->set(self::EXPIRATION_TIMESTAMP, $expirationTimestamp->getTimestamp());

        $this->logger->debug('PHP session synced with OAuth token expiration', [
            'expires_at' => $expirationTimestamp->format('Y-m-d H:i:s'),
        ]);
    }
}
