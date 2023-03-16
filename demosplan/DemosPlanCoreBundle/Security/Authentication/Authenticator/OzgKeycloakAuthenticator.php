<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserLogin;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakResponseInterface;
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

class OzgKeycloakAuthenticator extends OAuth2Authenticator implements AuthenticationEntrypointInterface
{
    private OzgKeycloakUserLogin $ozgKeycloakUserLogin;
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private RouterInterface $router;
    private KeycloakResponseInterface $keycloakResponse;

    public function __construct(
        OzgKeycloakUserLogin $ozgKeycloakUserLogin,
        KeycloakResponseInterface $keycloakResponse,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        RouterInterface $router
    ) {
        $this->ozgKeycloakUserLogin = $ozgKeycloakUserLogin;
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->router = $router;
        $this->keycloakResponse = $keycloakResponse;
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
        $this->logger->info('login attempt', ['accessToken' => $accessToken ?? null,]);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {
                try {
                    $this->entityManager->getConnection()->beginTransaction();
                    $this->keycloakResponse->create($client->fetchUserFromToken($accessToken));
                    $ozgKeycloakResponseValueObject = $this->keycloakResponse;

                    $user = $this->ozgKeycloakUserLogin->handleKeycloakData($ozgKeycloakResponseValueObject);
                    $this->entityManager->getConnection()->commit();
                    $request->getSession()->set('userId', $user->getId());

                    return $user;
                } catch (Exception $e) {
                    $this->entityManager->getConnection()->rollBack();
                    $this->logger->error(
                        'login failed',
                        [
                            'requestValues' => $ozgKeycloakResponseValueObject ?? null,
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
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the 'login'.
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            '/connect/keycloak_ozg', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
