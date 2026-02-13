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
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakStaticUserDataProvider;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
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
 *
 * Examples:
 *   /verfahren/login?keycloakTest=multi-org-user     - User with 3 organisations
 *   /verfahren/login?keycloakTest=dual-org-user      - User with 2 organisations
 *   /verfahren/login?keycloakTest=single-org-user    - User with 1 organisation
 *   /verfahren/login?keycloakTest=fachplaner-admin   - Planning agency admin
 *   /verfahren/login?keycloakTest=toeb-koordinator   - Public agency coordinator
 *   /verfahren/login?keycloakTest=private-person     - Citizen (private person)
 *
 * See OzgKeycloakStaticUserDataProvider for all available test users.
 *
 * IMPORTANT: This authenticator should only be enabled in development environments!
 */
class OzgKeycloakStaticAuthenticator extends AbstractAuthenticator
{
    use KeycloakAuthenticationSuccessTrait;

    public function __construct(
        private readonly CustomerServiceInterface $customerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly LoggerInterface $logger,
        private readonly OzgKeycloakUserDataMapper $ozgKeycloakUserDataMapper,
        private readonly RouterInterface $router,
        private readonly CurrentOrganisationService $currentOrganisationService,
        private readonly OzgKeycloakStaticUserDataProvider $userDataProvider,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only support requests with keycloakTest query parameter
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
            'responsibilityCount' => count($userData['responsibilities'] ?? []),
        ]);

        try {
            $this->entityManager->getConnection()->beginTransaction();
            $this->logger->info('Start of doctrine transaction (static Keycloak).');

            $customerSubdomain = $this->customerService->getCurrentCustomer()->getSubdomain();

            // Create a KeycloakResourceOwner from our static data
            $resourceOwner = new KeycloakResourceOwner($userData);
            $this->ozgKeycloakUserData->fill($resourceOwner, $customerSubdomain);

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
}
