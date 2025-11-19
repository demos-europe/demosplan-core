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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
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
        private readonly RoleHandler $roleHandler,
        private readonly ParameterBagInterface $parameterBag,
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
}
