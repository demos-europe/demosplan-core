<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Event\User\OrgaTypeChangedEvent;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;

/**
 * Subscribes to OrgaTypeChangedEvent and cleans up permissions for removed organization types.
 *
 * When an organization type is removed (e.g., changing from MUNICIPALITY to PLANNING_AGENCY),
 * the permissions for the roles of the old type need to be cleaned up from the access_control table.
 */
class OrgaTypePermissionsCleanupSubscriber extends BaseEventSubscriber
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly CustomerHandler $customerHandler,
        private readonly RoleHandler $roleHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrgaTypeChangedEvent::class => 'onOrgaTypeChanged',
        ];
    }

    /**
     * Handle organization type changes and cleanup permissions for removed types.
     */
    public function onOrgaTypeChanged(OrgaTypeChangedEvent $event): void
    {
        $updatedOrga = $event->getUpdatedOrga();
        $oldOrgaTypes = $event->getOldOrgaTypes();
        $currentCustomer = $this->customerHandler->getCurrentCustomer();

        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Processing organization type change', [
            'orgaId'   => $updatedOrga->getId(),
            'oldTypes' => $oldOrgaTypes,
        ]);

        // Get new accepted types in current customer
        $newTypes = $updatedOrga->getStatusInCustomers()
            ->filter(static fn (OrgaStatusInCustomer $status): bool => OrgaStatusInCustomer::STATUS_ACCEPTED === $status->getStatus()
                && $status->getCustomer() === $currentCustomer)
            ->map(static fn (OrgaStatusInCustomer $status): string => $status->getOrgaType()->getName())
            ->toArray();

        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Type comparison', [
            'oldTypes' => $oldOrgaTypes,
            'newTypes' => $newTypes,
        ]);

        // Find removed types
        $removedTypes = array_diff($oldOrgaTypes, $newTypes);

        if (empty($removedTypes)) {
            $this->logger->info('OrgaTypePermissionsCleanupSubscriber: No types removed, nothing to cleanup');

            return;
        }

        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Types removed, cleaning up permissions', [
            'removedTypes' => $removedTypes,
        ]);

        // Cleanup permissions for each removed type
        foreach ($removedTypes as $removedType) {
            $this->cleanupPermissionsForType($removedType, $updatedOrga, $currentCustomer);
        }
    }

    /**
     * Cleanup permissions for a specific removed organization type.
     */
    private function cleanupPermissionsForType(string $removedType, $updatedOrga, Customer $currentCustomer): void
    {
        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Processing removed type', [
            'removedType' => $removedType,
        ]);

        // Get roles for this removed type
        if (!isset(OrgaTypeInterface::ORGATYPE_ROLE[$removedType])) {
            $this->logger->warning('OrgaTypePermissionsCleanupSubscriber: No role mapping found for removed type', [
                'removedType' => $removedType,
            ]);

            return;
        }

        $roleCodesToRemove = OrgaTypeInterface::ORGATYPE_ROLE[$removedType];

        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Role codes for removed type', [
            'removedType' => $removedType,
            'roleCodes'   => $roleCodesToRemove,
        ]);

        // Convert role codes to Role objects
        $rolesToRemove = [];
        foreach ($roleCodesToRemove as $roleCode) {
            $role = $this->roleHandler->getRoleByCode($roleCode);
            if (null !== $role) {
                $rolesToRemove[] = $role;
                $this->logger->debug('OrgaTypePermissionsCleanupSubscriber: Role found', [
                    'roleCode' => $roleCode,
                    'roleId'   => $role->getId(),
                ]);
            } else {
                $this->logger->warning('OrgaTypePermissionsCleanupSubscriber: Role not found', [
                    'roleCode' => $roleCode,
                ]);
            }
        }

        // Remove permissions for these roles
        if (empty($rolesToRemove)) {
            $this->logger->warning('OrgaTypePermissionsCleanupSubscriber: No roles to remove permissions for', [
                'removedType' => $removedType,
            ]);

            return;
        }

        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Removing permissions', [
            'orgaId'     => $updatedOrga->getId(),
            'customerId' => $currentCustomer->getId(),
            'rolesCount' => count($rolesToRemove),
            'roleCodes'  => $roleCodesToRemove,
        ]);

        $this->accessControlService->removePermissions(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $updatedOrga,
            $currentCustomer,
            $rolesToRemove
        );

        $this->logger->info('OrgaTypePermissionsCleanupSubscriber: Successfully cleaned up permissions', [
            'orgaId'       => $updatedOrga->getId(),
            'removedType'  => $removedType,
            'removedRoles' => $roleCodesToRemove,
        ]);
    }
}
