<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Provider;

use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AiApiUserProvider implements UserProviderInterface
{
    public function __construct(
        protected readonly UserService $userService
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $username
     */
    public function loadUserByUsername($username): UserInterface
    {
        if (AiApiUser::AI_API_USER_LOGIN !== $username) {
            throw new UserNotFoundException('Invalid username');
        }

        return $this->userService->getValidUser($username);
    }

    /**
     * The AiApiUser is a FunctionalUser and does thus not have to be refreshed via DB
     * or any other means.
     *
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     */
    public function supportsClass($class): string
    {
        return AiApiUser::class;
    }
}
