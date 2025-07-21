<?php

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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

    public function shouldInjectTestJwt(): bool
    {
        return DemosPlanKernel::ENVIRONMENT_TEST === $this->kernel->getEnvironment()
            || DemosPlanKernel::ENVIRONMENT_DEV === $this->kernel->getEnvironment();
    }


    public function injectTokenExpirationIntoSession(Session $session, UserInterface $user): void
    {
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
        } catch (Exception $e) {
            $this->logger->warning('Failed to inject JWT token expiration into session', [
                'user'  => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

