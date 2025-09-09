<?php

declare(strict_types=1);

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
use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function refreshUser(UserInterface $user): ?UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class %s', $user::class));
        }

        // Return User object here as we want to have the User object in the Session instead,
        // as the SecurityUser is only meant to be used during Authentication.
        // In {@DemosPlanResponseListener::transformTokenUserObjectToSecurityUserObject()} we
        // transform the User object to a SecurityUser object to save it between requests.
        return $this->loadUserByLogin($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    public function loadUserByUsername(string $username): User
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): User
    {
        return $this->loadUserByLogin($identifier);
    }

    public function getSecurityUser(string $identifier): SecurityUser
    {
        return new SecurityUser($this->loadUserByLogin($identifier));
    }

    public function upgradePassword(UserInterface|PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $userEntity = $this->loadUserByLogin($user->getUserIdentifier());
        $this->userRepository->upgradePassword($userEntity, $newHashedPassword);
    }

    private function loadUserByLogin(string $login): User
    {
        // avoid database call for anonymous user
        if (AddonContractUserInterface::ANONYMOUS_USER_LOGIN === $login) {
            return new AnonymousUser();
        }

        $userEntity = $this->userRepository->findOneBy(['login' => $login]);
        if (!$userEntity) {
            throw new UserNotFoundException(sprintf('No user found for "%s"', $login));
        }

        return $userEntity;
    }
}
