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

use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Propagates the personal access token context attached to the request by
 * {@see \demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenRequestAuthenticator}
 * into the {@see Permissions} evaluator, after {@see ConfigurePermissionsListener} has initialised
 * permissions for the authenticated user.
 *
 * Priority 6 ensures this runs right after ConfigurePermissionsListener (priority 7) so the context
 * is in place before the controller — and any @DplanPermissions attribute — executes.
 */
#[AsEventListener(event: 'kernel.controller', priority: 6)]
class ConfigurePersonalAccessTokenContextListener
{
    public function __construct(private readonly Permissions $permissions)
    {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $context = $event->getRequest()->attributes->get(PersonalAccessTokenContext::REQUEST_ATTRIBUTE);
        $this->permissions->setApiTokenContext(
            $context instanceof PersonalAccessTokenContext ? $context : null
        );
    }
}
