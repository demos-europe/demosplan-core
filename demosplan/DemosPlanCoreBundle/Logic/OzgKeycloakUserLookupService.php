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

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserDataInterface;

/**
 * Service for looking up existing users during Keycloak authentication.
 */
class OzgKeycloakUserLookupService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function fetchExistingUserViaGatewayId(KeycloakUserDataInterface $ozgKeycloakUserData): ?User
    {
        return $this->userRepository->findOneBy(['gwId' => $ozgKeycloakUserData->getUserId()]);
    }

    public function fetchExistingUserViaLoginAttribute(KeycloakUserDataInterface $ozgKeycloakUserData): ?User
    {
        return $this->userRepository->findOneBy(['login' => $ozgKeycloakUserData->getUserName()]);
    }

    public function fetchExistingUserViaEmail(KeycloakUserDataInterface $ozgKeycloakUserData): ?User
    {
        return $this->userRepository->findOneBy(['email' => $ozgKeycloakUserData->getEmailAddress()]);
    }
}
