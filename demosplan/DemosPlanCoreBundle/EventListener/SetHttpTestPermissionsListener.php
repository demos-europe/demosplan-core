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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsEventListener(event: 'kernel.controller', priority: 5)]
class SetHttpTestPermissionsListener
{
    public const X_DPLAN_TEST_PERMISSIONS = 'x-dplan-test-permissions';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly PermissionsInterface $permissions,
        private readonly CurrentUserService $userService,
        private readonly DatabaseToolCollection $databaseToolCollection
    ) {
    }

    public function onKernelController(ControllerEvent $controllerEvent): void
    {
        if ($this->kernel->getEnvironment() !== 'test') {
            return;
        }

        $request = $controllerEvent->getRequest();

        if ($request->server->has('TEST_USER')) {
            $fixtures = $this->databaseToolCollection->get()->loadAllFixtures(['TestData'])->getReferenceRepository();
            $testUser = $fixtures->getReference( LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
            $this->permissions->initPermissions($testUser);
            $this->userService->setUser($testUser);

        }

        if ($request->server->has(self::X_DPLAN_TEST_PERMISSIONS)) {
            $permissions = $request->server->get(self::X_DPLAN_TEST_PERMISSIONS);
            $this->permissions->enablePermissions(explode(',', $permissions));
        }



    }
}
