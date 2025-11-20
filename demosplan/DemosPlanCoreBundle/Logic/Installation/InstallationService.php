<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Installation;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\PendingPermission;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\PendingPermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service responsible for installation and initial configuration tasks.
 *
 * This service handles automatic configuration during installation,
 * such as enabling default permissions based on environment settings.
 */
class InstallationService extends CoreService
{
    /**
     * Default role code for procedure creation permission.
     * RMOPSA = Planning Agency Admin (Fachplanerorganisation Administrator).
     */
    private const DEFAULT_PROCEDURE_CREATION_ROLE = 'RMOPSA';

    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly PendingPermissionRepository $pendingPermissionRepository,
        private readonly RoleHandler $roleHandler,
    ) {
    }

    /**
     * Enables procedure creation permission for planning agencies if configured via environment variable.
     *
     * This method checks the 'auto_enable_procedure_creation' parameter (set via AUTO_ENABLE_PROCEDURE_CREATION env var).
     * If enabled, it grants the 'feature_admin_new_procedure' permission to all planning agencies
     * with the specified role (default: RMOPSA) for the given customer.
     *
     * This is intended to be called:
     * - During initial installation
     * - When a new customer is created
     * - Manually via a command if needed
     *
     * @param CustomerInterface $customer The customer for which to enable the permission
     * @param string|null       $roleCode Optional role code (default: RMOPSA)
     * @param bool              $dryRun   If true, only simulate without making actual changes
     * @param bool              $force    If true, enable permission regardless of env variable configuration
     *
     * @return array Array of organization names that were updated
     */
    public function enableProcedureCreationIfConfigured(
        CustomerInterface $customer,
        ?string $roleCode = null,
        bool $dryRun = false,
        bool $force = false,
    ): array {
        // Check if auto-enable is configured (unless forced)
        $autoEnable = $this->parameterBag->get('auto_enable_procedure_creation');

        if (!$force && !$autoEnable) {
            $this->logger->info(
                'Procedure creation auto-enable is disabled. Skipping automatic permission setup.',
                ['customer' => $customer->getName()]
            );

            return [];
        }

        // Use default role if none specified
        $roleCode = $roleCode ?? self::DEFAULT_PROCEDURE_CREATION_ROLE;

        // Get the role object
        $role = $this->roleHandler->getRoleByCode($roleCode);

        if (null === $role) {
            $this->logger->error(
                'Cannot enable procedure creation: role not found',
                [
                    'roleCode' => $roleCode,
                    'customer' => $customer->getName(),
                ]
            );

            return [];
        }

        $this->logger->info(
            'Auto-enabling procedure creation permission for customer',
            [
                'customer'   => $customer->getName(),
                'role'       => $roleCode,
                'permission' => AccessControlService::CREATE_PROCEDURES_PERMISSION,
                'dryRun'     => $dryRun,
            ]
        );

        try {
            // Enable the permission for all eligible organizations
            $updatedOrgas = $this->accessControlService->enablePermissionCustomerOrgaRole(
                AccessControlService::CREATE_PROCEDURES_PERMISSION,
                $customer,
                $role,
                $dryRun
            );

            $orgaNames = array_map(fn ($orga) => $orga->getName(), $updatedOrgas);

            $this->logger->info(
                'Successfully enabled procedure creation permission',
                [
                    'customer'             => $customer->getName(),
                    'updatedOrganizations' => $orgaNames,
                    'count'                => count($updatedOrgas),
                ]
            );

            return $orgaNames;
        } catch (Exception $e) {
            $this->logger->error(
                'Failed to enable procedure creation permission',
                [
                    'customer'  => $customer->getName(),
                    'role'      => $roleCode,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Check if auto-enable procedure creation is configured.
     *
     * @return bool True if AUTO_ENABLE_PROCEDURE_CREATION is set to true
     */
    public function isProcedureCreationAutoEnableConfigured(): bool
    {
        return (bool) $this->parameterBag->get('auto_enable_procedure_creation');
    }

    /**
     * Queue a pending permission to be applied when matching organizations are created.
     *
     * This method stores a "permission intent" that will be automatically applied
     * to organizations of the specified type when they are created for this customer.
     *
     * @param CustomerInterface $customer    The customer for which to queue the permission
     * @param string            $permission  The permission name (e.g., 'feature_admin_new_procedure')
     * @param string            $roleCode    The role code (e.g., 'RMOPSA')
     * @param string            $orgaType    The organization type (e.g., 'PLANNING_AGENCY', 'PUBLIC_AGENCY')
     * @param string|null       $description Optional description of why this permission is queued
     * @param bool              $autoDelete  If true, delete pending permission after first application
     *
     * @return PendingPermission The created pending permission entity
     */
    public function queuePendingPermission(
        CustomerInterface $customer,
        string $permission,
        string $roleCode = 'RMOPSA',
        string $orgaType = OrgaType::PLANNING_AGENCY,
        ?string $description = null,
        bool $autoDelete = false,
    ): PendingPermission {
        // Check if this pending permission already exists
        if ($this->pendingPermissionRepository->exists($customer, $permission, $roleCode, $orgaType)) {
            $this->logger->info(
                'Pending permission already queued, skipping duplicate',
                [
                    'customer'   => $customer->getName(),
                    'permission' => $permission,
                    'roleCode'   => $roleCode,
                    'orgaType'   => $orgaType,
                ]
            );

            // Return existing pending permission
            $existing = $this->pendingPermissionRepository->findOneBy([
                'customer'   => $customer,
                'permission' => $permission,
                'roleCode'   => $roleCode,
                'orgaType'   => $orgaType,
            ]);

            return $existing;
        }

        $pendingPermission = new PendingPermission();
        $pendingPermission->setCustomer($customer);
        $pendingPermission->setPermission($permission);
        $pendingPermission->setRoleCode($roleCode);
        $pendingPermission->setOrgaType($orgaType);
        $pendingPermission->setCreatedAt(new DateTime());
        $pendingPermission->setDescription($description);
        $pendingPermission->setAutoDelete($autoDelete);

        $this->entityManager->persist($pendingPermission);
        $this->entityManager->flush();

        $this->logger->info('Queued pending permission for future application', [
            'customer'   => $customer->getName(),
            'permission' => $permission,
            'roleCode'   => $roleCode,
            'orgaType'   => $orgaType,
            'autoDelete' => $autoDelete,
        ]);

        return $pendingPermission;
    }

    /**
     * Queue multiple pending permissions at once.
     *
     * @param CustomerInterface                                                                                                  $customer    The customer for which to queue permissions
     * @param array<int, array{permission: string, roleCode: string, orgaType: string, description?: string, autoDelete?: bool}> $permissions Array of permission configurations
     *
     * @return array<int, PendingPermission> Array of created pending permissions
     */
    public function queueMultiplePendingPermissions(
        CustomerInterface $customer,
        array $permissions,
    ): array {
        $queuedPermissions = [];

        foreach ($permissions as $permissionConfig) {
            $queuedPermissions[] = $this->queuePendingPermission(
                $customer,
                $permissionConfig['permission'],
                $permissionConfig['roleCode'] ?? 'RMOPSA',
                $permissionConfig['orgaType'] ?? OrgaType::PLANNING_AGENCY,
                $permissionConfig['description'] ?? null,
                $permissionConfig['autoDelete'] ?? false
            );
        }

        return $queuedPermissions;
    }

    /**
     * Apply any pending permissions for a newly created organization.
     *
     * This method checks if there are any pending permissions queued for the organization's
     * type(s) and applies them to the organization.
     *
     * @param OrgaInterface     $organization The organization to apply permissions to
     * @param CustomerInterface $customer     The customer context
     *
     * @return array<string, mixed> Result containing applied permissions and statistics
     */
    public function applyPendingPermissions(
        OrgaInterface $organization,
        CustomerInterface $customer,
    ): array {
        $orgaTypes = $organization->getTypes($customer->getSubdomain(), true);
        $appliedPermissions = [];
        $failedPermissions = [];
        $autoDeletedCount = 0;

        foreach ($orgaTypes as $orgaTypeName) {
            // $orgaTypeName is already a string (e.g., "PLANNING_AGENCY")
            $pendingPermissions = $this->pendingPermissionRepository
                ->findByCustomerAndOrgaType($customer, $orgaTypeName);

            if (empty($pendingPermissions)) {
                continue;
            }

            $this->logger->info(
                'Found pending permissions to apply',
                [
                    'customer'     => $customer->getName(),
                    'organization' => $organization->getName(),
                    'orgaType'     => $orgaTypeName,
                    'count'        => count($pendingPermissions),
                ]
            );

            foreach ($pendingPermissions as $pending) {
                $role = $this->roleHandler->getRoleByCode($pending->getRoleCode());

                if (null === $role) {
                    $this->logger->warning(
                        'Cannot apply pending permission: role not found',
                        [
                            'roleCode'   => $pending->getRoleCode(),
                            'permission' => $pending->getPermission(),
                        ]
                    );
                    $failedPermissions[] = [
                        'permission' => $pending->getPermission(),
                        'reason'     => 'Role not found: '.$pending->getRoleCode(),
                    ];
                    continue;
                }

                try {
                    // Apply the permission to this specific organization
                    $updatedOrgas = $this->accessControlService->enablePermissionCustomerOrgaRole(
                        $pending->getPermission(),
                        $customer,
                        $role,
                        false,
                        $organization->getId() // Target specific organization
                    );

                    if (!empty($updatedOrgas)) {
                        $appliedPermissions[] = [
                            'permission' => $pending->getPermission(),
                            'roleCode'   => $pending->getRoleCode(),
                            'orgaType'   => $orgaTypeName,
                        ];

                        $this->logger->info('Applied pending permission', [
                            'customer'     => $customer->getName(),
                            'organization' => $organization->getName(),
                            'permission'   => $pending->getPermission(),
                            'roleCode'     => $pending->getRoleCode(),
                        ]);

                        // Delete if marked for auto-deletion
                        if ($pending->isAutoDelete()) {
                            $this->entityManager->remove($pending);
                            ++$autoDeletedCount;
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->error('Failed to apply pending permission', [
                        'permission' => $pending->getPermission(),
                        'error'      => $e->getMessage(),
                    ]);
                    $failedPermissions[] = [
                        'permission' => $pending->getPermission(),
                        'reason'     => $e->getMessage(),
                    ];
                }
            }
        }

        if ($autoDeletedCount > 0) {
            $this->entityManager->flush();
            $this->logger->info(
                'Auto-deleted pending permissions after application',
                ['count' => $autoDeletedCount]
            );
        }

        return [
            'applied' => $appliedPermissions,
            'failed'  => $failedPermissions,
            'stats'   => [
                'appliedCount'      => count($appliedPermissions),
                'failedCount'       => count($failedPermissions),
                'autoDeletedCount'  => $autoDeletedCount,
            ],
        ];
    }

    /**
     * Get all pending permissions for a customer.
     *
     * @param CustomerInterface $customer The customer
     *
     * @return array<int, PendingPermission> Array of pending permissions
     */
    public function getPendingPermissions(CustomerInterface $customer): array
    {
        return $this->pendingPermissionRepository->findByCustomer($customer);
    }

    /**
     * Clear all pending permissions for a customer.
     *
     * @param CustomerInterface $customer The customer
     *
     * @return int Number of deleted pending permissions
     */
    public function clearPendingPermissions(CustomerInterface $customer): int
    {
        $count = $this->pendingPermissionRepository->deleteByCustomer($customer);

        $this->logger->info('Cleared pending permissions for customer', [
            'customer' => $customer->getName(),
            'count'    => $count,
        ]);

        return $count;
    }
}
