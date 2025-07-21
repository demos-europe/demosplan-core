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

use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Request listener that automatically injects JWT token expiration into session
 * for authenticated users when not already present.
 */
class TokenExpirationRequestListener implements EventSubscriberInterface
{
    public const ACCESS_TOKEN_EXPIRATION_TIMESTAMP = 'accessTokenExpirationTimestamp';
    private const JWT_EXPIRATION_TIMESTAMP = 'exp';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only handle main requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Skip if no session is available
        if (!$session->isStarted()) {
            return;
        }

        // Skip if user is not authenticated
        $user = $this->security->getUser();
        if (null === $user) {
            return;
        }

        // Check if in test environment
        if ('dev' !== $this->kernel->getEnvironment()) {
            return;
        }

        // Try to get JWT token expiration and store in session
        $this->injectTokenExpirationIntoSession($session, $user);
    }

    private function injectTokenExpirationIntoSession(Session $session, UserInterface $user): void
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
