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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Service responsible for injecting JWT token expiration timestamps into user sessions
 * in non-production environments. This enables frontend auto-logout functionality
 * for development and testing purposes.
 */
class TokenExpirationInjection
{
    public const ACCESS_TOKEN_EXPIRATION_TIMESTAMP = 'accessTokenExpirationTimestamp';
    private const JWT_EXPIRATION_TIMESTAMP = 'exp';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly LoggerInterface $logger,
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
        return DemosPlanKernel::ENVIRONMENT_TEST === $this->kernel->getEnvironment()
            || DemosPlanKernel::ENVIRONMENT_DEV === $this->kernel->getEnvironment();
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
            // Create JWT token for the authenticated user
            $jwtToken = $this->jwtTokenManager->create($user);

            // Parse the token to get expiration
            $payload = $this->jwtTokenManager->parse($jwtToken);
            $expiration = $payload[self::JWT_EXPIRATION_TIMESTAMP] ?? null;

            if (null !== $expiration) {
                $session->set(self::ACCESS_TOKEN_EXPIRATION_TIMESTAMP, (int) $expiration);

                $this->logger->debug('JWT token expiration injected into session', [
                    'user'       => $user->getUserIdentifier(),
                    'expiration' => $expiration,
                ]);
            }

            $this->logger->debug('No expiration found in JWT token', [
                'user' => $user->getUserIdentifier(),
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to inject JWT token expiration into session', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
