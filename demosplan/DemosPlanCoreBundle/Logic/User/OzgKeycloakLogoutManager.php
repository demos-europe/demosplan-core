<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Stores Keycloak tokens in session and builds logout URLs with customer subdomains.
 */
class OzgKeycloakLogoutManager
{
    public const EXPIRATION_TIMESTAMP = 'expirationTimestamp';
    public const KEYCLOAK_TOKEN = 'keycloakToken';

    private const POST_LOGOUT_REDIRECT_URI = 'post_logout_redirect_uri=https://';
    private const ID_TOKEN_HINT = 'id_token_hint=';

    /** @var int Default session expiration time when not set in parameters (6 hours) */
    private const DEFAULT_SESSION_LIFETIME_SECONDS = 21600;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
        private readonly CurrentUserService $currentUser,
        private readonly CustomerService $customerService,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * Check if Keycloak logout is configured for this environment.
     */
    public function isKeycloakConfigured(): bool
    {
        return '' !== $this->parameterBag->get('oauth_keycloak_logout_route');
    }

    public function shouldSkipInProductionWithoutKeycloak()
    {
        return DemosPlanKernel::ENVIRONMENT_PROD === $this->kernel->getEnvironment()
            && !$this->isKeycloakConfigured();
    }

    public function hasLogoutWarningPermission(): bool
    {
        return $this->currentUser->hasPermission('feature_auto_logout_warning');
    }

    /**
     * Stores expiration timestamp into the user session.
     */
    public function injectTokenExpirationIntoSession(SessionInterface $session, UserInterface $user): void
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

            // Set the custom expiration directly in session
            $session->set(self::EXPIRATION_TIMESTAMP, $expirationTimestamp);

            $this->logger->debug('Expiration timestamp injected into session', [
                'user'       => $user->getUserIdentifier(),
                'expiration' => $expirationTimestamp,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to inject expiration timestamp into session', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function hasValidToken(SessionInterface $session): bool
    {
        $tokenExpires = $session->get(self::EXPIRATION_TIMESTAMP);

        if (!$tokenExpires) {
            $this->logger->debug('No token expiration found in session');

            return false;
        }

        $currentTime = time();
        $isValid = $currentTime <= $tokenExpires;

        $this->logger->debug('Token validation result', [
            'current_time'      => date('Y-m-d H:i:s', $currentTime),
            'token_expires'     => date('Y-m-d H:i:s', $tokenExpires),
            'seconds_remaining' => $tokenExpires - $currentTime,
            'is_valid'          => $isValid,
        ]);

        return $isValid;
    }

    public function storeTokenAndExpirationInSession(SessionInterface $session, array $tokenValues): void
    {
        if (isset($tokenValues['id_token'])) {
            $session->set(self::KEYCLOAK_TOKEN, $tokenValues['id_token']);
            $this->logger->info('Adding keycloak id_token to session');
        } else {
            $this->logger->warning('No keycloak id_token found in token values, not storing in session');
        }
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
}
