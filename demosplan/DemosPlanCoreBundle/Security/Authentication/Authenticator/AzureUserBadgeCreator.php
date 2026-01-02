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

use demosplan\DemosPlanCoreBundle\Logic\AzureUserDataMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\AzureUserData;
use Exception;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Simple user badge creator for already provisioned Azure OAuth users.
 */
class AzureUserBadgeCreator
{
    public function __construct(
        private readonly AzureUserData $azureUserData,
        private readonly LoggerInterface $logger,
        private readonly AzureUserDataMapper $azureUserDataMapper,
    ) {
    }

    public function createAzureUserBadge(string $userIdentifier, ResourceOwnerInterface $resourceOwner, Request $request): UserBadge
    {
        return new UserBadge($userIdentifier, function () use ($resourceOwner, $request) {
            try {
                $this->azureUserData->fill($resourceOwner);
                $this->logger->info('Processing Azure OAuth authentication: '.$this->azureUserData);

                $user = $this->azureUserDataMapper->mapUserData($this->azureUserData);
                $request->getSession()->set('userId', $user->getId());

                return $user;
            } catch (Exception $e) {
                $this->logger->error('Azure OAuth authentication failed', [
                    'userData'  => $this->azureUserData->__toString(),
                    'exception' => $e->getMessage(),
                ]);
                throw new AuthenticationException('Azure OAuth authentication failed: '.$e->getMessage());
            }
        });
    }
}
