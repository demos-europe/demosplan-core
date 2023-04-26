<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CurrentUserProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Token\DemosToken;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class CurrentUserService implements CurrentUserInterface, CurrentUserProviderInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var CustomerHandler
     */
    private $customerHandler;
    /**
     * @var PermissionsInterface
     */
    private $permissions;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        CustomerHandler $customerHandler,
        PermissionsInterface $permissions,
        TokenStorageInterface $tokenStorage
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->customerHandler = $customerHandler;
        $this->permissions = $permissions;
        $this->logger = $logger;
    }

    public function getUser(): User
    {
        $user = $this->getToken()->getUser();
        if (!$user instanceof User) {
            throw new UserNotFoundException('Invalid User');
        }

        return $user;
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function setUser(User $user, Customer $customer = null): void
    {
        $customer = $customer ?? $this->customerHandler->getCurrentCustomer();
        $token = new DemosToken($user, $customer);
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

    /**
     * @throws UserNotFoundException
     */
    private function getToken(): DemosToken
    {
        $token = $this->tokenStorage->getToken();
        // convert PostAuthenticationToken generated during symfony login to DemosToken
        if ($token instanceof PostAuthenticationToken) {
            $token = new DemosToken($token->getUser());
        }
        if (!$token instanceof DemosToken) {
            $this->logger->error('invalid user', [$token, debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 30)]);
            throw new UserNotFoundException('Token could not be found');
        }

        return $token;
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
