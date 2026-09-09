<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rebuilds the request-scoped context an export needs when it runs in a background worker instead of
 * a web request: customer (subdomain), acting user, permissions and - where the export is bound to a
 * single procedure - the current procedure.
 *
 * Both parts matter for the resulting document. Without the customer, permissions stored per customer
 * in access_control resolve against whatever the worker's default subdomain happens to be. Without
 * {@link PermissionsInterface::setProcedurePermissions()} every procedure-scoped permission stays
 * disabled, and the export silently comes back anonymised or with columns missing.
 */
class ExportJobContextRestorer
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserService $currentUserService,
        private readonly CustomerService $customerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureService $procedureService,
    ) {
    }

    /**
     * Restore user, customer and permissions. Pass the procedure id for exports scoped to a single
     * procedure; leave it null for exports covering a selection (the procedure export sets the
     * procedure itself, per procedure, while building the archive).
     */
    public function restore(string $userId, string $customerId, ?string $procedureId = null): void
    {
        $customer = $this->customerService->findCustomerById($customerId);
        // Resolve the customer the same way a request would: several permission lookups go through
        // CustomerService, which reads the subdomain off the global config.
        $this->globalConfig->setSubdomain($customer->getSubdomain());

        $user = $this->entityManager->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new UserNotFoundException("Export job user not found: {$userId}");
        }
        // Assign explicitly rather than relying on DoctrineUserListener::postLoad(), which resolves
        // the customer from the subdomain that was active when the user got loaded.
        $user->setCurrentCustomer($customer);
        $this->currentUserService->setUser($user, $customer);

        $procedure = null;
        if (null !== $procedureId) {
            $procedure = $this->procedureService->getProcedure($procedureId);
            if (!$procedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($procedureId);
            }
        }
        // Always assign, including the null case: a worker handles many messages in one process, so
        // an unset procedure here means the previous message's procedure would be evaluated instead.
        $this->permissions->setProcedure($procedure);

        $this->permissions->initPermissions($user);

        if ($procedure instanceof Procedure) {
            $this->permissions->setProcedurePermissions();
            $this->currentProcedureService->setProcedure($procedure);
        }
    }
}
