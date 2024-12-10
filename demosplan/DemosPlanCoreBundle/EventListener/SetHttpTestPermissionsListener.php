<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\SecurityUserProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: 'kernel.controller', priority: 5)]
class SetHttpTestPermissionsListener
{
    public const X_DPLAN_TEST_PERMISSIONS = 'x-dplan-test-permissions';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly PermissionsInterface $permissions, private readonly CurrentUserInterface $currentUser, private readonly UserService $userService, private readonly SecurityUserProvider $securityUserProvider, private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onKernelController(ControllerEvent $controllerEvent): void
    {
        if ('test' !== $this->kernel->getEnvironment()) {
            return;
        }

        $request = $controllerEvent->getRequest();

        if ($request->server->has('USER_ID')) {
            $user = $this->userService->getSingleUser($request->server->get('USER_ID'));
            $this->currentUser->setUser($user);

            $existingToken = $this->tokenStorage->getToken();
            $securityUser = new SecurityUser($user);

            $existingToken->setUser($securityUser);

            $this->permissions->initPermissions($user);
        }

        if ($request->server->has(self::X_DPLAN_TEST_PERMISSIONS)) {
            $permissions = $request->server->get(self::X_DPLAN_TEST_PERMISSIONS);
            $this->permissions->enablePermissions(explode(',', $permissions));
        }
    }
}
