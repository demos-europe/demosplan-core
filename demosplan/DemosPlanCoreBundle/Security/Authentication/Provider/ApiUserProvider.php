<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Provider;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface as AddonContractUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ApiUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // avoid database call if anonymous user calls API
        if (AddonContractUserInterface::ANONYMOUS_USER_LOGIN === $identifier) {
            return new AnonymousUser();
        }

        $user = $this->userService->findDistinctUserByEmailOrLogin($identifier);

        if (!$user instanceof User) {
            $user = new AnonymousUser();
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
