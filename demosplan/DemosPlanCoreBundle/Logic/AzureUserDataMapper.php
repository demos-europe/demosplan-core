<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\AzureUserData;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Simple Azure OAuth user mapper for SCIM-provisioned users.
 * Only finds existing users, does not create or update them.
 */
class AzureUserDataMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserService $userService,
    ) {
    }

    /**
     * Finds existing user by email from Azure OAuth data.
     *
     * @throws AuthenticationException when user is not found
     */
    public function mapUserData(AzureUserData $azureUserData): UserInterface
    {
        // Find existing user by email
        $user = $this->userService->findDistinctUserByEmailOrLogin($azureUserData->getEmailAddress());

        if ($user instanceof User) {
            $this->logger->info('Found existing SCIM-provisioned user for Azure OAuth', [
                'email'    => $azureUserData->getEmailAddress(),
                'objectId' => $azureUserData->getObjectId(),
            ]);

            return $user;
        }

        // User not found - this should not happen with SCIM provisioning
        $this->logger->warning('Azure OAuth user not found in system', [
            'email'    => $azureUserData->getEmailAddress(),
            'objectId' => $azureUserData->getObjectId(),
        ]);

        throw new AuthenticationException('User not found in system. Users must be provisioned before Azure OAuth login.');
    }
}
