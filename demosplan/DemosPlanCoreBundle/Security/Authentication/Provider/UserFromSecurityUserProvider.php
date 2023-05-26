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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserFromSecurityUserProvider
{
    private ?User $user = null;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserRepository $userRepository
    ) {
    }

    public function get(): ?User
    {
        if (null === $this->user) {
            $this->user = $this->fromToken($this->tokenStorage->getToken());
        }

        return $this->user;
    }

    public function fromToken(TokenInterface $token): ?User
    {
        if (!$token->getUser() instanceof UserInterface) {
            return null;
        }

        return $this->userRepository->findOneBy(['login' => $token->getUser()->getUserIdentifier()]);
    }

    public function fromSecurityUser(SecurityUser $securityUser): ?User
    {
        return $this->userRepository->findOneBy(['login' => $securityUser->getUserIdentifier()]);
    }
}
