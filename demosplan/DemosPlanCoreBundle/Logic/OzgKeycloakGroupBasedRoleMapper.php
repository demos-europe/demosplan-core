<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Service responsible for extracting and mapping user roles from Keycloak group information.
 * This handles the legacy group-based role detection for OZG Keycloak authentication.
 */
class OzgKeycloakGroupBasedRoleMapper
{
    private const ROLETITLE_TO_ROLECODE = [
        'Mandanten Administration'          => RoleInterface::CUSTOMER_MASTER_USER,
        'Organisationsadministration'       => RoleInterface::ORGANISATION_ADMINISTRATION,
        'Fachplanung Planungsbüro'          => RoleInterface::PRIVATE_PLANNING_AGENCY,
        'Fachplanung Administration'        => RoleInterface::PLANNING_AGENCY_ADMIN,
        'Fachplanung Sachbearbeitung'       => RoleInterface::PLANNING_AGENCY_WORKER,
        'Institutions Koordination'         => RoleInterface::PUBLIC_AGENCY_COORDINATION,
        'Institutions Sachbearbeitung'      => RoleInterface::PUBLIC_AGENCY_WORKER,
        'Support'                           => RoleInterface::PLATFORM_SUPPORT,
        'Redaktion'                         => RoleInterface::CONTENT_EDITOR,
        'Privatperson-Angemeldet'           => RoleInterface::CITIZEN,
        'Fachliche Leitstelle'              => RoleInterface::PROCEDURE_CONTROL_UNIT,
        'Datenerfassung'                    => RoleInterface::PROCEDURE_DATA_INPUT,
    ];

    public function __construct(
        private readonly GlobalConfig $globalConfig,
        private readonly LoggerInterface $logger,
        private readonly RoleRepository $roleRepository,
    ) {
    }

    /**
     * Map roles based on group information from token.
     *
     * @param array<string, array<int, string>> $rolesOfCustomer
     *
     * @return array<int, Role>
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function mapGroupBasedRoles(array $rolesOfCustomer, string $subdomain): array
    {
        [$recognizedRoleCodes, $unIdentifiedRoles] = $this->extractRoleCodesFromGroups(
            $rolesOfCustomer,
            $subdomain
        );

        if ([] !== $unIdentifiedRoles) {
            $this->logger->error('at least one non recognizable role was requested!', $unIdentifiedRoles);
        }

        $this->logger->info('Recognized Roles: ', [$recognizedRoleCodes]);
        $requestedRoles = $this->filterNonAvailableRolesInProject($recognizedRoleCodes);

        if ([] === $requestedRoles) {
            throw new AuthenticationCredentialsNotFoundException('no roles could be identified');
        }

        $this->logger->info('Finally recognized Roles: ', [$requestedRoles]);

        return $requestedRoles;
    }

    /**
     * Extract role codes from customer groups.
     *
     * @param array<string, array<int, string>> $rolesOfCustomer
     *
     * @return array{0: array<int, string>, 1: array<int, string>} [recognizedRoleCodes, unIdentifiedRoles]
     */
    public function extractRoleCodesFromGroups(array $rolesOfCustomer, string $subdomain): array
    {
        $recognizedRoleCodes = [];
        $unIdentifiedRoles = [];

        if (!array_key_exists($subdomain, $rolesOfCustomer)) {
            return [$recognizedRoleCodes, $unIdentifiedRoles];
        }

        foreach ($rolesOfCustomer[$subdomain] as $roleName) {
            if (null === $roleName || '' === $roleName) {
                continue;
            }

            $this->logger->info("Role found for subdomain {$subdomain}: {$roleName}");

            if (array_key_exists($roleName, self::ROLETITLE_TO_ROLECODE)) {
                $this->logger->info("Role recognized: {$roleName}");
                $recognizedRoleCodes[] = self::ROLETITLE_TO_ROLECODE[$roleName];
            } else {
                $this->logger->info("Role not recognized: {$roleName}");
                $unIdentifiedRoles[] = $roleName;
            }
        }

        return [$recognizedRoleCodes, $unIdentifiedRoles];
    }

    /**
     * Filter out roles that are not available in the current project.
     *
     * @param array<int, string> $requestedRoleCodes
     *
     * @return array<int, Role>
     */
    private function filterNonAvailableRolesInProject(array $requestedRoleCodes): array
    {
        $unavailableRoles = [];
        $availableRequestedRoles = [];
        foreach ($requestedRoleCodes as $roleCode) {
            if (in_array($roleCode, $this->globalConfig->getRolesAllowed(), true)) {
                $this->logger->info('try to fetch role entity for role code', [$roleCode]);
                $availableRequestedRoles[] = $this->roleRepository->findOneBy(['code' => $roleCode]);
                $this->logger->info('current available requested roles', [$availableRequestedRoles]);
            } else {
                $this->logger->info('try to fetch role entity for not allowed role code', [$roleCode]);
                $unavailableRoles[] = $this->roleRepository->findOneBy(['code' => $roleCode]);
                $this->logger->info('current unavailable requested roles', [$unavailableRoles]);
            }
        }
        if ([] !== $unavailableRoles) {
            $this->logger->info('the following requested roles are not available in project', $unavailableRoles);
        }

        return $availableRequestedRoles;
    }
}
