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
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AiApiUserProvider implements UserProviderInterface
{
    public function __construct(
        protected readonly UserService $userService,
        protected readonly CustomerService $customerService
    ) {
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (AiApiUser::AI_API_USER_LOGIN !== $identifier) {
            throw new UserNotFoundException('Invalid username');
        }

        return $this->getApiUser();
    }

    /**
     * The AiApiUser is a FunctionalUser and does thus not have to be refreshed via DB
     * or any other means.
     *
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->getApiUser();
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     */
    public function supportsClass($class): bool
    {
        return AiApiUser::class === $class;
    }

    private function getApiUser(): AiApiUser
    {
        return new AiApiUser($this->customerService->getCurrentCustomer());
    }
}
