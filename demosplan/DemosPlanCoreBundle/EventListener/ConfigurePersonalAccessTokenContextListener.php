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
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Propagates the API token context attached to the request by whichever token authenticator handled
 * it into the {@see Permissions} evaluator, after {@see ConfigurePermissionsListener} has initialised
 * permissions for the authenticated user.
 *
 * Priority 6 ensures this runs right after ConfigurePermissionsListener (priority 7) so the context
 * is in place before the controller — and any @DplanPermissions attribute — executes.
 *
 * The two request attributes are mutually exclusive: the authenticators dispatch on the token's
 * literal prefix, so at most one of them ever ran.
 */
#[AsEventListener(event: 'kernel.controller', priority: 6)]
class ConfigurePersonalAccessTokenContextListener
{
    private const REQUEST_ATTRIBUTES = [
        PersonalAccessTokenContext::REQUEST_ATTRIBUTE,
        ProcedureIntegrationTokenContext::REQUEST_ATTRIBUTE,
    ];

    public function __construct(private readonly Permissions $permissions)
    {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        foreach (self::REQUEST_ATTRIBUTES as $attribute) {
            $context = $request->attributes->get($attribute);
            if ($context instanceof ApiTokenContextInterface) {
                $this->permissions->setApiTokenContext($context);

                return;
            }
        }

        $this->permissions->setApiTokenContext(null);
    }
}
