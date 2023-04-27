<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Services\CurrentUserProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\UserFromSecurityUserProvider;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Token\NotAuthenticatedToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CurrentUserService implements CurrentUserInterface, CurrentUserProviderInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(
        private readonly UserFromSecurityUserProvider $userFromSecurityUserProvider,
        PermissionsInterface $permissions,
        TokenStorageInterface $tokenStorage
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->permissions = $permissions;
    }

    public function getUser(): User
    {
        $user = $this->getToken()->getUser();

        if ($user instanceof SecurityUser) {
            $user = $this->userFromSecurityUserProvider->fromSecurityUser($user);
        }

        if (!$user instanceof User) {
            $user = new AnonymousUser();
        }

        return $user;
    }

    public function setUser(User $user, Customer $customer = null): void
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
