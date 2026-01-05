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
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AzureAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly LoggerInterface $logger,
        private readonly RouterInterface $router,
        private readonly AzureUserBadgeCreator $azureUserBadgeCreator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_azure_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('azure');
        $accessToken = $this->fetchAccessToken($client);
        $this->logger->info('Azure OAuth login attempt', ['accessToken' => $accessToken]);

        $userIdentifier = $accessToken->getToken();
        $resourceOwner = $client->fetchUserFromToken($accessToken);

        return new SelfValidatingPassport(
            $this->azureUserBadgeCreator->createAzureUserBadge($userIdentifier, $resourceOwner, $request)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $this->logger->info('User was logged in via Azure OAuth', ['id' => $user->getId(), 'roles' => $user->getDplanRolesString()]);

        // Propagate user login to session
        $request->getSession()->set('userId', $user->getId());

        return $this->createSuccessRedirect();
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Login via Azure OAuth failed', ['exception' => $exception]);

        return $this->createFailureRedirect();
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the Azure OAuth login.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            '/connect/azure', // Azure OAuth start endpoint
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    private function createSuccessRedirect(): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('core_home_loggedin'));
    }

    private function createFailureRedirect(): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('core_login_idp_error'));
    }
}
