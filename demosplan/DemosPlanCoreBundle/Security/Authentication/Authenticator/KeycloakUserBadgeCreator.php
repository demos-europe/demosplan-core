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

use demosplan\DemosPlanCoreBundle\Logic\KeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class KeycloakUserBadgeCreator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KeycloakUserData $keycloakUserData,
        private readonly LoggerInterface $logger,
        private readonly KeycloakUserDataMapper $keycloakUserDataMapper,
    ) {
    }

    public function createKeycloakUserBadge(string $userIdentifier, ResourceOwnerInterface $resourceOwner, Request $request): UserBadge
    {
        return new UserBadge($userIdentifier, function () use ($resourceOwner, $request) {
            try {
                $this->entityManager->getConnection()->beginTransaction();
                $this->logger->info('Start of doctrine transaction.');

                $this->keycloakUserData->fill($resourceOwner);
                $this->logger->info('Found user data: '.$this->keycloakUserData);
                $user = $this->keycloakUserDataMapper->mapUserData($this->keycloakUserData);

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
                        'requestValues' => $this->keycloakUserData ?? null,
                        'exception'     => $e,
                    ]
                );
                throw new AuthenticationException('You shall not pass!');
            }
        });
    }
}
