<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Provider;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanUserBundle\Logic\UserService;

class ApiUserProvider implements UserProviderInterface
{
    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $login
     */
    public function loadUserByUsername($login): UserInterface
    {
        // avoid database call if anonymous user calls API
        if (User::ANONYMOUS_USER_NAME === $login) {
            return new AnonymousUser();
        }

        $user = $this->userService->findDistinctUserByEmailOrLogin($login);

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
        return $user;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     */
    public function supportsClass($class): string
    {
        return User::class;
    }
}
