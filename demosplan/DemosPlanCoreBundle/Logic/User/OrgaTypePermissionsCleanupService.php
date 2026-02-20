<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;

/**
 * Service to clean up permissions when an organization type is removed.
 *
 * When an organization type changes from 'accepted' to non-accepted status,
 * the permissions for the roles of that type need to be removed from the access_control table.
 */
class OrgaTypePermissionsCleanupService extends CoreService
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly RoleHandler $roleHandler,
    ) {
    }

    /**
     * Cleanup permissions for a specific removed organization type.
     *
     * @param string        $removedType The organization type name (e.g., 'OLAUTH', 'OPAUTH')
     * @param OrgaInterface $orga        The organization
     * @param Customer      $customer    The customer context
     */
    public function cleanupPermissionsForRemovedType(string $removedType, OrgaInterface $orga, Customer $customer): void
    {
        // Get roles for this removed type from the ORGATYPE_ROLE mapping
        if (!isset(OrgaTypeInterface::ORGATYPE_ROLE[$removedType])) {
            $this->logger->warning('OrgaTypePermissionsCleanupService: No role mapping found for removed type', [
                'removedType' => $removedType,
            ]);

            return;
        }

        $roleCodesToRemove = OrgaTypeInterface::ORGATYPE_ROLE[$removedType];

        // Convert role codes to Role objects
        $rolesToRemove = [];
        foreach ($roleCodesToRemove as $roleCode) {
            $role = $this->roleHandler->getRoleByCode($roleCode);
            if (null !== $role) {
                $rolesToRemove[] = $role;
            } else {
                $this->logger->warning('OrgaTypePermissionsCleanupService: Role not found', [
                    'roleCode' => $roleCode,
                ]);
            }
        }

        if (empty($rolesToRemove)) {
            $this->logger->warning('OrgaTypePermissionsCleanupService: No roles to remove permissions for', [
                'removedType' => $removedType,
            ]);

            return;
        }

        $this->logger->info('OrgaTypePermissionsCleanupService: Removing permissions', [
            'orgaId'      => $orga->getId(),
            'customerId'  => $customer->getId(),
            'removedType' => $removedType,
            'roleCodes'   => $roleCodesToRemove,
        ]);

        $this->accessControlService->removePermissions(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $orga,
            $customer,
            $rolesToRemove
        );
    }
}
