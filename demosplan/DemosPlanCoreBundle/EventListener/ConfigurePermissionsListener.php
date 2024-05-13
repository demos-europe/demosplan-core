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
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'kernel.controller', priority: 7)]
class ConfigurePermissionsListener
{
    public function __construct(
        private readonly CurrentUserService $currentUserService,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    public function onKernelController(): void
    {
        $user = $this->currentUserService->getUser();
        $this->permissions->initPermissions($user);
    }
}
