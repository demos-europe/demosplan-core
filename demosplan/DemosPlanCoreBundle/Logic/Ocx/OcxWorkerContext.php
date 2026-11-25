<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use LogicException;

/**
 * Gives the worker the context a delivery was sent in: customer, API user, organisation, procedure
 * and the same permission gate as the intake request.
 *
 * Enter again after every EntityManager::clear(), the restored user and procedure are detached
 * then. Leave when done: the worker runs other messages in the same process, and the gate would
 * deny their permissions.
 */
class OcxWorkerContext
{
    /**
     * Projects alias the interface to their own subclass; the concrete class would be another instance.
     */
    public function __construct(
        private readonly ExportJobContextRestorer $contextRestorer,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    public function enter(string $userId, string $customerId, string $procedureId, ?string $organisationId): void
    {
        $permissions = $this->getPermissions();
        $this->contextRestorer->restore($userId, $customerId, $procedureId, $organisationId);
        $permissions->setApiTokenContext(new OcxRequestContext());
    }

    public function leave(): void
    {
        $this->getPermissions()->setApiTokenContext(null);
    }

    private function getPermissions(): Permissions
    {
        if (!$this->permissions instanceof Permissions) {
            throw new LogicException('The OCX worker context requires '.Permissions::class);
        }

        return $this->permissions;
    }
}
