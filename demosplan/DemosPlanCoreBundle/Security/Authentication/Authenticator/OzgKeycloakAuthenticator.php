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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CustomerServiceInterface;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\PendingRequestCacheService;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakClientFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

class OzgKeycloakAuthenticator extends AbstractOzgKeycloakAuthenticator implements AuthenticationEntryPointInterface
{
    private ?AccessToken $pendingAccessToken = null;

    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        CurrentOrganisationService $currentOrganisationService,
        MessageBagInterface $messageBag,
        OAuthTokenStorageService $oauthTokenStorageService,
        PendingRequestCacheService $pendingRequestCacheService,
        OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly OzgKeycloakClientFactory $ozgKeycloakClientFactory,
        private readonly CustomerServiceInterface $customerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly OzgKeycloakUserDataMapper $ozgKeycloakUserDataMapper,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct(
            $logger,
            $router,
            $currentOrganisationService,
            $messageBag,
            $oauthTokenStorageService,
            $pendingRequestCacheService,
            $ozgKeycloakSessionManager,
        );
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_keycloak_ozg_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->ozgKeycloakClientFactory->createForCurrentCustomer();
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

            $customerSubdomain = $this->customerService->getCurrentCustomer()->getSubdomain();
            $keycloakClientId = $this->ozgKeycloakClientFactory->getClientIdForCurrentCustomer(
                $this->parameterBag->get('oauth_keycloak_client_id')
            );

            // Create ResourceOwner with complete JWT payload (includes resource_access)
            $resourceOwner = new KeycloakResourceOwner($decodedJwtPayload);
            $this->ozgKeycloakUserData->fill($resourceOwner, $customerSubdomain, $keycloakClientId);
            $this->logger->info('Found user data: '.$this->ozgKeycloakUserData);
            $user = $this->ozgKeycloakUserDataMapper->mapUserData($this->ozgKeycloakUserData);

            $this->entityManager->getConnection()->commit();
            $this->logger->info('doctrine transaction commit.');
            $request->getSession()->set('userId', $user->getId());
            $this->pendingAccessToken = $accessToken;
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->logger->info('doctrine transaction rollback.');
            $this->logger->error(
                'login failed',
                [
                    'requestValues' => $this->ozgKeycloakUserData,
                    'exception'     => $e,
                ]
            );
            throw new AuthenticationException('You shall not pass!');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn () => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        try {
            $accessToken = $this->pendingAccessToken;
            $this->pendingAccessToken = null;

            return $this->handleAuthenticationSuccess($request, $token, $accessToken);
        } catch (Exception $e) {
            return $this->onAuthenticationFailure($request, new AuthenticationException($e->getMessage(), 0, $e));
        }
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
