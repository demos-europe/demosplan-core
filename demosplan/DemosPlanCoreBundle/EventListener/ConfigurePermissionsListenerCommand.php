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
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'console.command')]
class ConfigurePermissionsListenerCommand
{
    public function __construct(
        private readonly PermissionsInterface $permissions,
    ) {
    }

     public function __invoke(): void
     {
         // For console commands no real user is available, but we need to
         // initialize permissions nonetheless to receive the global project permissions.
         $this->permissions->initPermissions(new AnonymousUser());
     }

}
