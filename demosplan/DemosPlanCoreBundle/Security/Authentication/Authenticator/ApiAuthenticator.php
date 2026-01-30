<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Hybrid API Authenticator that supports both session and JWT authentication.
 *
 * For browser clients: Uses existing session authentication (no JWT needed in JavaScript)
 * For external API clients: Uses JWT token in X-JWT-Authorization header
 *
 * This approach keeps APIs stateless for external consumers while allowing
 * browser clients to use their existing session without exposing JWT to JavaScript.
 */
class ApiAuthenticator extends JWTAuthenticator
{
    private const SESSION_USER_ID_KEY = 'userId';

    private bool $authenticatedViaSession = false;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $eventDispatcher,
        TokenExtractorInterface $tokenExtractor,
        UserProviderInterface $userProvider,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        ?TranslatorInterface $translator = null,
    ) {
        parent::__construct($jwtManager, $eventDispatcher, $tokenExtractor, $userProvider, $translator);
    }

    public function supports(Request $request): bool
    {
        // Support requests with either a valid session or JWT token
        return $this->hasValidSession($request) || parent::supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        // First, try session-based authentication (for browser clients)
        if ($this->hasValidSession($request)) {
            $user = $this->getUserFromSession($request);
            if (null !== $user) {
                $this->logger->debug('API request authenticated via session', [
                    'user' => $user->getLogin(),
                ]);

                $this->authenticatedViaSession = true;

                return new SelfValidatingPassport(
                    new UserBadge($user->getLogin(), fn () => $user)
                );
            }
        }

        // Fall back to JWT authentication (for external API clients)
        $this->logger->debug('API request falling back to JWT authentication');
        $this->authenticatedViaSession = false;

        return parent::authenticate($request);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        // For session-based auth, create a standard PostAuthenticationToken
        // (no JWT token string available)
        if ($this->authenticatedViaSession) {
            return new PostAuthenticationToken(
                $passport->getUser(),
                $firewallName,
                $passport->getUser()->getRoles()
            );
        }

        // For JWT auth, let parent create the JWT-specific token
        return parent::createToken($passport, $firewallName);
    }

    /**
     * Check if the request has a valid session with an authenticated user.
     */
    private function hasValidSession(Request $request): bool
    {
        return $request->hasSession()
            && $request->getSession()->has(self::SESSION_USER_ID_KEY);
    }

    /**
     * Get the authenticated user from the session.
     */
    private function getUserFromSession(Request $request): ?User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_ID_KEY);

        if (null === $userId) {
            return null;
        }

        // Use findOneBy with deleted check to match JWT authentication behavior
        // This ensures deleted users cannot authenticate via session
        $user = $this->userRepository->findOneBy([
            'id' => $userId,
            'deleted' => false,
        ]);

        return $user instanceof User ? $user : null;
    }
}
