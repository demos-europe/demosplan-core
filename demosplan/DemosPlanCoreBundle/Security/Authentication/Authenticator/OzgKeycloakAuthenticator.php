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

use DemosEurope\DemosplanAddon\Contracts\Services\CustomerServiceInterface;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakLogoutManager;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Psr\Log\LoggerInterface;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class OzgKeycloakAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use KeycloakAuthenticationSuccessTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly CustomerServiceInterface $customerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly LoggerInterface $logger,
        private readonly OzgKeycloakUserDataMapper $ozgKeycloakUserDataMapper,
        private readonly RouterInterface $router,
        private readonly OzgKeycloakLogoutManager $keycloakLogoutManager,
        private readonly CurrentOrganisationService $currentOrganisationService,
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
        $this->logger->info('login attempt', ['accessToken' => $accessToken]);

        // Execute user creation immediately instead of deferring it
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $this->logger->info('Start of doctrine transaction.');

            // Decode the JWT access token to get ALL claims including resource_access
            // Parse without verification since Keycloak signed it with its own keys
            $parser = new Parser(new JoseEncoder());
            $token = $parser->parse($accessToken->getToken());
            $decodedJwtPayload = $token->claims()->all();
            $this->logger->info('raw token', [$decodedJwtPayload]);

            $tokenValues = $accessToken->getValues();
            $this->keycloakLogoutManager->storeTokenAndExpirationInSession($request->getSession(), $tokenValues);

            $customerSubdomain = $this->customerService->getCurrentCustomer()->getSubdomain();

            // Create ResourceOwner with complete JWT payload (includes resource_access)
            $resourceOwner = new KeycloakResourceOwner($decodedJwtPayload);
            $this->ozgKeycloakUserData->fill($resourceOwner, $customerSubdomain);
            $this->logger->info('Found user data: '.$this->ozgKeycloakUserData);
            $user = $this->ozgKeycloakUserDataMapper->mapUserData($this->ozgKeycloakUserData);

            $this->entityManager->getConnection()->commit();
            $this->logger->info('doctrine transaction commit.');
            $request->getSession()->set('userId', $user->getId());
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

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn () => $user)
        );
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
