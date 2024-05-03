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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener(event: 'kernel.controller', priority: 6)]
class AccessProcedureListener
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    public function onKernelController(ControllerEvent $controllerEvent): void
    {
        if (null === $this->currentProcedureService->getProcedure()) {
            return;
        }

        $this->permissions->setProcedurePermissions();
        // check whether user may participate in this procedure via Consultation Token
        // this is temporary and will be better be solved via an SecurityVoter
        $invitedProcedures = $controllerEvent->getRequest()->getSession()->get('invitedProcedures', []);
        $this->permissions->evaluateUserInvitedInProcedure($invitedProcedures);

        // check whether user has access to the procedure
        $this->permissions->checkProcedurePermission();
    }
}
