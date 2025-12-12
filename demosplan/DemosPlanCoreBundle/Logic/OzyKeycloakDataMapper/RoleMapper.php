<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper;

use Psr\Log\LoggerInterface;

/**
 * Maps technical role codes from Keycloak resource_access claim to readable role names.
 * This replaces the group-based role mapping for better integration with Mein Unternehmenskonto.
 */
class RoleMapper
{
    /**
     * Maps technical role codes from resource_access to readable role names.
     */
    private const TECHNICAL_ROLE_TO_READABLE = [
        'FP-A'  => 'Fachplanung Administration',
        'FP-PB' => 'Fachplanung Planungsbüro',
        'FP-SB' => 'Fachplanung Sachbearbeitung',
        'I-K'   => 'Institutions Koordination',
        'I-SB'  => 'Institutions Sachbearbeitung',
        'M-A'   => 'Mandanten Administration',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Extract roles from resource_access claim.
     *
     * Example structure:
     * {
     *   "resource_access": {
     *     "diplan-develop-beteiligung-bau": {
     *       "roles": ["FP-SB", "I-K"]
     *     },
     *     "diplan-develop-beteiligung-rog": {
     *       "roles": ["FP-A"]
     *     }
     *   }
     * }
     *
     * @param array<string, mixed> $resourceAccess    The resource_access claim from the JWT token
     * @param string               $keycloakClientId  The configured client ID to look for
     * @param string               $customerSubdomain The customer subdomain (e.g., 'hh', 'be', 'by') to store roles under
     *
     * @return array<string, array<int, string>> Array with customerSubdomain as key and array of readable role names as value, or empty array if no roles found
     */
    public function mapResourceAccessRoles(array $resourceAccess, string $keycloakClientId, string $customerSubdomain): array
    {
        // If no client ID is configured, skip resource_access extraction (fallback to groups)
        if ('' === $keycloakClientId) {
            $this->logger->info('No oauth_keycloak_client_id configured, skipping resource_access extraction');

            return [];
        }

        // Check if the configured client ID exists in resource_access
        if (!isset($resourceAccess[$keycloakClientId])) {
            $this->logger->warning("Configured client ID '{$keycloakClientId}' not found in resource_access");

            return [];
        }

        $clientData = $resourceAccess[$keycloakClientId];
        $this->logger->info("Found configured client '{$keycloakClientId}' in resource_access");

        if (!isset($clientData['roles']) || !is_array($clientData['roles'])) {
            $this->logger->warning("No roles array found in resource_access for client: {$keycloakClientId}");

            return [];
        }

        $customerRoleRelations = [];
        foreach ($clientData['roles'] as $technicalRole) {
            if (array_key_exists($technicalRole, self::TECHNICAL_ROLE_TO_READABLE)) {
                $readableRoleName = self::TECHNICAL_ROLE_TO_READABLE[$technicalRole];
                $customerRoleRelations[$customerSubdomain][] = $readableRoleName;
                $this->logger->info("Mapped technical role {$technicalRole} to {$readableRoleName} for customer subdomain {$customerSubdomain}");
            } else {
                $this->logger->warning("Unknown technical role in resource_access: {$technicalRole}");
            }
        }

        return $customerRoleRelations;
    }
}
