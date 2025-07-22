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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Service responsible for injecting test expiration timestamps into user sessions
 * in non-production environments. This enables frontend auto-logout functionality
 * for development and testing purposes.
 */
class TokenExpirationInjection
{
    public const ACCESS_TOKEN_EXPIRATION_TIMESTAMP = 'accessTokenExpirationTimestamp';

    /** @var int Session expiration time for testing (120 minutes) */
    private const TEST_SESSION_LIFETIME_SECONDS = 7200;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
        private readonly CurrentUserService $currentUser,
    ) {
    }

    /**
     * Determines if token expiration should be injected based on the current environment.
     * Only enables injection in development and test environments.
     *
     * @return bool True if injection should occur, false otherwise
     */
    public function shouldInjectTestJwtTokenExpiration(): bool
    {
        if (!$this->displayLogoutWarning()) {
            return false;
        }

        return DemosPlanKernel::ENVIRONMENT_TEST === $this->kernel->getEnvironment()
            || DemosPlanKernel::ENVIRONMENT_DEV === $this->kernel->getEnvironment();
    }

    public function displayLogoutWarning(): bool
    {
        return $this->currentUser->hasPermission('feature_auto_logout_warning');
    }

    /**
     * Injects JWT token expiration timestamp into the user session.
     * Creates a new JWT token for the user and extracts its expiration time.
     */
    public function injectTokenExpirationIntoSession(SessionInterface $session, UserInterface $user): void
    {
        // Skip if expiration is already present in session
        if ($session->has(self::ACCESS_TOKEN_EXPIRATION_TIMESTAMP)) {
            return;
        }

        try {
            $metadataBag = $session->getMetadataBag();
            $sessionCreated = $metadataBag->getCreated();
            $sessionLifetime = $metadataBag->getLifetime() ?: self::TEST_SESSION_LIFETIME_SECONDS;
            $expirationTimestamp = $sessionCreated + $sessionLifetime;

            // Set the custom expiration directly in session
            $session->set(self::ACCESS_TOKEN_EXPIRATION_TIMESTAMP, $expirationTimestamp);

            $this->logger->debug('Expiration timestamp injected into session for testing', [
                'user'       => $user->getUserIdentifier(),
                'expiration' => $expirationTimestamp,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to inject expiration timestamp into session for testing', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
