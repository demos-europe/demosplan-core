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
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use LogicException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Hands the token context of the request to the permission evaluator. Runs after
 * {@see ConfigurePermissionsListener} (priority 7) and before the permission check of the
 * controller ({@see CheckPermissionEventSubscriber}, priority 4).
 */
#[AsEventListener(event: 'kernel.controller', priority: 6)]
readonly class ConfigureApiTokenContextListener
{
    /**
     * Projects alias the interface to their own subclass; the concrete class would be another instance.
     */
    public function __construct(private PermissionsInterface $permissions)
    {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $context = $event->getRequest()->attributes->get(ApiTokenContextInterface::REQUEST_ATTRIBUTE);
        if (!$this->permissions instanceof Permissions) {
            if ($context instanceof ApiTokenContextInterface) {
                throw new LogicException('API token contexts require '.Permissions::class);
            }

            return;
        }
        if (!$context instanceof ApiTokenContextInterface) {
            $this->permissions->setApiTokenContext(null);

            return;
        }

        $this->permissions->setApiTokenContext($context);
    }
}
