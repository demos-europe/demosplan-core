<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CurrentUserProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\UserFromSecurityUserProvider;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Token\NotAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CurrentUserService implements CurrentUserInterface, CurrentUserProviderInterface
{
    public function __construct(private readonly UserFromSecurityUserProvider $userFromSecurityUserProvider, private readonly PermissionsInterface $permissions, private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function getUser(): User
    {
        $user = $this->getToken()->getUser();

        if ($user instanceof SecurityUser) {
            $user = $this->userFromSecurityUserProvider->fromSecurityUser($user);
            // swap real User in token to be used later on when injecting TokenInterface
            $this->getToken()->setUser($user);
        }

        if (!$user instanceof User) {
            $user = new AnonymousUser();
        }

        return $user;
    }

    public function setUser(UserInterface $user, CustomerInterface $customer = null): void
    {
        $token = $this->getToken();
        $token->setUser($user);
        $this->tokenStorage->setToken($token);
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions(): PermissionsInterface
    {
        return $this->permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions->hasPermission($permission);
    }

    private function getToken(): TokenInterface
    {
        return $this->tokenStorage->getToken() ?? new NotAuthenticatedToken();
    }

    public function hasAnyPermissions(string ...$permissions): bool
    {
        return $this->permissions->hasPermissions($permissions, 'OR');
    }

    public function hasAllPermissions(string ...$permissions): bool
    {
        return $this->permissions->hasPermissions($permissions);
    }
}
