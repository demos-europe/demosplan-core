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

use DateTime;
use DateTimeZone;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CustomerServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\PendingRequestCacheService;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakStaticUserDataProvider;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Static authenticator for testing Keycloak login without a real Keycloak server.
 *
 * This authenticator allows testing of the Keycloak authentication flow,
 * including multi-responsibility (multi-organisation) login scenarios.
 *
 * Usage:
 *   Add ?keycloakTest=<user-key> to any route.
 *   Add &simulateBufferedData=1 to also simulate a pending request from token expiry.
 *
 * Examples:
 *   /?keycloakTest=multi-org-user                          - Fresh login with 4 organisations
 *   /?keycloakTest=dual-org-user                           - Fresh login with 2 organisations
 *   /?keycloakTest=dual-org-user&simulateBufferedData=1    - Re-auth with buffered data
 *   /?keycloakTest=single-org-user                         - Fresh login with 1 organisation
 *
 * See OzgKeycloakStaticUserDataProvider for all available test users.
 *
 * IMPORTANT: This authenticator should only be enabled in development environments!
 */
class OzgKeycloakStaticAuthenticator extends AbstractOzgKeycloakAuthenticator
{
    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        CurrentOrganisationService $currentOrganisationService,
        MessageBagInterface $messageBag,
        OAuthTokenStorageService $oauthTokenStorageService,
        PendingRequestCacheService $pendingRequestCacheService,
        OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly CustomerServiceInterface $customerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly OzgKeycloakUserDataMapper $ozgKeycloakUserDataMapper,
        private readonly OzgKeycloakStaticUserDataProvider $userDataProvider,
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly SecretEncryptor $tokenEncryptionService,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%oauth_token_timezone%')]
        private readonly string $tokenTimezoneString,
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
        if ('prod' === $this->environment) {
            return false;
        }

        return $request->query->has('keycloakTest');
    }

    public function authenticate(Request $request): Passport
    {
        $testUserKey = $request->query->get('keycloakTest');
        $userData = $this->userDataProvider->getUserData($testUserKey);

        if (null === $userData) {
            $availableKeys = implode(', ', $this->userDataProvider->getAvailableUserKeys());
            $this->logger->error('Static Keycloak test user not found', [
                'requestedKey'  => $testUserKey,
                'availableKeys' => $availableKeys,
            ]);
            throw new AuthenticationException(sprintf('Test user "%s" not found. Available users: %s', $testUserKey, $availableKeys));
        }

        $this->logger->info('Static Keycloak login attempt', [
            'testUserKey'         => $testUserKey,
            'userId'              => $userData['sub'],
            'email'               => $userData['email'],
            'responsibilityCount' => count($userData['fachbezug'] ?? []),
        ]);

        try {
            $this->entityManager->getConnection()->beginTransaction();
            $this->logger->info('Start of doctrine transaction (static Keycloak).');

            $customerSubdomain = $this->customerService->getCurrentCustomer()->getSubdomain();

            // Create a KeycloakResourceOwner from our static data
            $resourceOwner = new KeycloakResourceOwner($userData);
            // Static test users always use 'dplan-test' as client ID in resource_access
            $this->ozgKeycloakUserData->fill($resourceOwner, $customerSubdomain, 'dplan-test');

            $this->logger->info('Static Keycloak user data: '.$this->ozgKeycloakUserData);
            $user = $this->ozgKeycloakUserDataMapper->mapUserData($this->ozgKeycloakUserData);

            $this->entityManager->getConnection()->commit();
            $this->logger->info('Doctrine transaction commit (static Keycloak).');

            $request->getSession()->set('userId', $user->getId());
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->logger->info('Doctrine transaction rollback (static Keycloak).');
            $this->logger->error(
                'Static Keycloak login failed',
                [
                    'testUserKey' => $testUserKey,
                    'exception'   => $e,
                ]
            );
            throw new AuthenticationException('Static Keycloak authentication failed: '.$e->getMessage());
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn () => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        try {
            if ($request->query->has('simulateBufferedData')) {
                $this->createFakePendingData($token);
            }

            return $this->handleAuthenticationSuccess($request, $token, null);
        } catch (Exception $e) {
            return $this->onAuthenticationFailure($request, new AuthenticationException($e->getMessage(), 0, $e));
        }
    }

    /**
     * Create a real OAuthToken entity with fake pending data so that
     * handleAuthenticationSuccess() finds it and exercises the full re-auth flow.
     */
    private function createFakePendingData(TokenInterface $token): void
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $oauthToken = $this->oauthTokenRepository->findByUserId($user->getId());
        if (null === $oauthToken) {
            $oauthToken = new OAuthToken();
            $oauthToken->setUser($user);
            $this->entityManager->persist($oauthToken);
        }

        // Set pending fields directly instead of using storePendingRequest(),
        // which calls clearTokens() and leaves the entity without tokens —
        // causing the expiry listener to force re-auth on the next request.
        $timezone = new DateTimeZone($this->tokenTimezoneString);

        $oauthToken->setPendingPageUrl($this->router->generate('DemosPlan_faq'));
        $oauthToken->setPendingRequestUrl('/api/2.0/statement');
        $oauthToken->setPendingRequestMethod('POST');
        $oauthToken->setPendingRequestContentType('application/vnd.api+json');
        $fakeBody = json_encode([
            'data' => [
                'type'       => 'Statement',
                'attributes' => [
                    'text' => 'Dies ist ein Testtext der nach der erneuten Authentifizierung wiederhergestellt werden soll.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $oauthToken->setPendingRequestBody($this->tokenEncryptionService->encrypt($fakeBody));
        $oauthToken->setPendingRequestTimestamp(new DateTime('now', $timezone));

        // Set fake future expiry dates so the expiry listener doesn't force re-auth
        $futureExpiry = (new DateTime('now', $timezone))->modify('+1 hour');
        $oauthToken->setAccessTokenExpiresAt($futureExpiry);
        $oauthToken->setRefreshTokenExpiresAt((clone $futureExpiry)->modify('+1 hour'));

        $firstOrga = $user->getOrganisations()->first();
        if (false !== $firstOrga) {
            $oauthToken->setSelectedOrganisation($firstOrga);
        }

        $this->entityManager->flush();

        $this->logger->info('Static Keycloak: fake pending data created for re-auth simulation', [
            'userId'  => $user->getId(),
            'pageUrl' => $oauthToken->getPendingPageUrl(),
            'orgaId'  => false !== $firstOrga ? $firstOrga->getId() : null,
        ]);
    }
}
