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

use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class OzgKeycloakAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly LoggerInterface $logger,
        private readonly OzgKeycloakUserDataMapper $ozgKeycloakUserDataMapper,
        private readonly RouterInterface $router
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_keycloak_ozg_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('keycloak_ozg');
        $accessToken = $this->fetchAccessToken($client);
        $this->logger->info('login attempt', ['accessToken' => $accessToken ?? null]);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {
                try {
                    $this->entityManager->getConnection()->beginTransaction();
                    $this->logger->info('Start of doctrine transaction.');
                    $this->logger->info('raw token', [$client->fetchUserFromToken($accessToken)->toArray()]);

                    $this->ozgKeycloakUserData->fill($client->fetchUserFromToken($accessToken));
                    $this->logger->info('Found user data: '.$this->ozgKeycloakUserData);
                    $user = $this->ozgKeycloakUserDataMapper->mapUserData($this->ozgKeycloakUserData);

                    $this->entityManager->getConnection()->commit();
                    $this->logger->info('doctrine transaction commit.');
                    $request->getSession()->set('userId', $user->getId());

                    return $user;
                } catch (Exception $e) {
                    $this->entityManager->getConnection()->rollBack();
                    $this->logger->info('doctrine transaction rollback.');
                    $this->logger->error(
                        'login failed',
                        [
                            'requestValues' => $this->ozgKeycloakUserData ?? null,
                            'exception'     => $e,
                        ]
                    );
                    throw new AuthenticationException('You shall not pass!');
                }
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('core_home_loggedin');

        return new RedirectResponse($targetUrl);

        // or, on success, let the request continue to be handled by the controller
        // return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Login via Keycloak failed', ['exception' => $exception]);
        $targetUrl = $this->router->generate('core_login_idp_error');

        return new RedirectResponse($targetUrl);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the 'login'.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            '/connect/keycloak_ozg', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
