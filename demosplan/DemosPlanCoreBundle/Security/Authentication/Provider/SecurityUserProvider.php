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

use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {
    }

    public function refreshUser(UserInterface $user): ?UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class %s', $user::class));
        }

        return new SecurityUser($this->loadUserByLogin($user->getUsername()));
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    public function loadUserByUsername(string $username): SecurityUser
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        return new SecurityUser($this->loadUserByLogin($identifier));
    }

    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void
    {
        $userEntity = $this->loadUserByLogin($user->getUsername());
        // set the new encoded password on the User object
        $userEntity->setPassword($newEncodedPassword);
        $userEntity->setAlternativeLoginPassword($newEncodedPassword);

        // execute the queries on the database
        $this->entityManager->flush();
    }

    private function loadUserByLogin(string $login): User
    {
        $userEntity = $this->userRepository->findOneBy(['login' => $login]);
        if (!$userEntity) {
            throw new UserNotFoundException(sprintf('No user found for "%s"', $login));
        }

        return $userEntity;
    }
}
