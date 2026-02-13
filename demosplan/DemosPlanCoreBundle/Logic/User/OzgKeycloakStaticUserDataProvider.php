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

/**
 * Provides hardcoded test user data for simulating Keycloak login.
 *
 * This class is used by OzgKeycloakStaticAuthenticator to enable local testing
 * of the authentication flow without requiring a real Keycloak server.
 *
 * Usage: Add ?keycloakTest=<user-key> to any route.
 * Example: /?keycloakTest=multi-org-user
 */
class OzgKeycloakStaticUserDataProvider
{
    private const KEYCLOAK_CLIENT = 'dplan-test';

    /**
     * Build a test user payload with sensible defaults.
     *
     * @param array<string>        $roles     Keycloak role codes (FP-A, FP-SB, I-K, I-SB, M-A)
     * @param array<string, mixed> $overrides Fields to override/add (organisation, responsibilities, etc.)
     *
     * @return array<string, mixed>
     */
    private static function user(
        string $sub,
        string $username,
        string $givenName,
        string $familyName,
        array $roles,
        array $overrides = [],
    ): array {
        return array_merge([
            'sub'                => $sub,
            'preferred_username' => $username,
            'given_name'         => $givenName,
            'family_name'        => $familyName,
            'email'              => $username,
            'organisation'       => [],
            'responsibilities'   => [],
            'resource_access'    => [] !== $roles
                ? [self::KEYCLOAK_CLIENT => ['roles' => $roles]]
                : [],
        ], $overrides);
    }

    /**
     * Hardcoded test users for simulating Keycloak login.
     *
     * Multi-org logic: cartesian product of organisation x responsibilities determines orgs.
     * Fallback: organisation-only, responsibilities-only, or single organisationId.
     *
     * Technical role codes:
     * - FP-A  = Fachplanung Administration
     * - FP-PB = Fachplanung Planungsbüro
     * - FP-SB = Fachplanung Sachbearbeitung
     * - I-K   = Institutions Koordination
     * - I-SB  = Institutions Sachbearbeitung
     * - M-A   = Mandanten Administration
     *
     * Note: oauth_keycloak_client_id must be set to 'dplan-test' in config_dev_container.yml
     *
     * @return array<string, array<string, mixed>>
     */
    private static function buildAvailableUsers(): array
    {
        return [
            // 2 affiliations x 2 responsibilities = 4 orgs (Fachplaner Admin)
            'multi-org-user' => self::user(
                'keycloak-test-multi-org-001', 'multi.orga@test.de', 'Multi', 'Orga-Tester',
                ['FP-A', 'FP-SB'],
                [
                    'organisation' => [
                        ['id' => 'TEST.ORGA.ALPHA', 'name' => 'Test Organisation Alpha'],
                        ['id' => 'TEST.ORGA.BETA', 'name' => 'Test Organisation Beta'],
                    ],
                    'responsibilities' => [
                        ['id' => 'WATER', 'name' => 'Wasserwirtschaft'],
                        ['id' => 'LITTER', 'name' => 'Abfallwirtschaft'],
                    ],
                ],
            ),

            // 2 affiliations x 0 responsibilities = 2 orgs (ToeB Koordinator)
            'dual-org-user' => self::user(
                'keycloak-test-dual-org-001', 'dual.orga@test.de', 'Dual', 'Orga-Tester',
                ['I-K', 'I-SB'],
                [
                    'organisation' => [
                        ['id' => 'TEST.ORGA.ONE', 'name' => 'Test Organisation Eins'],
                        ['id' => 'TEST.ORGA.TWO', 'name' => 'Test Organisation Zwei'],
                    ],
                ],
            ),

            // 2 affiliations with legacy organisationId fallback (ToeB + FP-A)
            'dual-org-user-fpa-toebk' => self::user(
                'keycloak-test-dual-org-002', 'dual.orga2@test.de', 'Dual FPA-ToebK', 'Orga-Tester',
                ['I-K', 'I-SB', 'FP-A'],
                [
                    'organisationId'   => '123456',
                    'organisationName' => 'KC Organame',
                    'organisation'     => [
                        ['id' => 'TEST.ORGA.ONE', 'name' => 'Test Organisation Eins'],
                        ['id' => 'TEST.ORGA.TWO', 'name' => 'Test Organisation Zwei'],
                    ],
                ],
            ),

            // Single org via organisationId fallback (no arrays)
            'single-org-user' => self::user(
                'keycloak-test-single-org-001', 'single.orga@test.de', 'Single', 'Orga-Tester',
                ['FP-A'],
                [
                    'organisationId'   => 'TEST.ORGA.SINGLE',
                    'organisationName' => 'Test Organisation Single',
                ],
            ),

            // Single affiliation, no responsibilities
            'affiliations-only-user' => self::user(
                'keycloak-test-aff-only-001', 'aff.only@test.de', 'Affiliation', 'Only-Tester',
                ['FP-A'],
                [
                    'organisation' => [
                        ['id' => 'TEST.ORGA.AFFONLY', 'name' => 'Test Affiliation Only Org'],
                    ],
                ],
            ),

            // Fachplaner Admin - legacy single-org
            'fachplaner-admin' => self::user(
                'keycloak-test-fachplaner-001', 'fachplaner.admin@test.de', 'Fachplaner', 'Admin',
                ['FP-A', 'FP-SB'],
                [
                    'organisationId'   => 'TEST.PLANUNGSBUERO',
                    'organisationName' => 'Test Planungsbüro GmbH',
                ],
            ),

            // ToeB Koordinator - legacy single-org
            'toeb-koordinator' => self::user(
                'keycloak-test-toeb-001', 'toeb.koordinator@test.de', 'TöB', 'Koordinator',
                ['I-K', 'I-SB'],
                [
                    'organisationId'   => 'TEST.BEHOERDE',
                    'organisationName' => 'Test Behörde',
                ],
            ),

            // Private person (citizen)
            'private-person' => self::user(
                'keycloak-test-citizen-001', 'max.mustermann@test.de', 'Max', 'Mustermann',
                [],
                [
                    'organisationId'   => '',
                    'organisationName' => '',
                    'isPrivatePerson'  => 'true',
                ],
            ),

            // Multi-organisation ToeB: 2 affiliations (no responsibilities)
            'multi-toeb' => self::user(
                'keycloak-test-multi-toeb-001', 'multi.toeb@test.de', 'Multi', 'TöB-Tester',
                ['I-K', 'I-SB'],
                [
                    'organisation' => [
                        ['id' => 'TEST.BEHOERDE.UMWELT', 'name' => 'Umweltbehörde Test'],
                        ['id' => 'TEST.BEHOERDE.BAU', 'name' => 'Baubehörde Test'],
                    ],
                ],
            ),
        ];
    }

    /**
     * Get user data for a given test user key.
     *
     * @return array<string, mixed>|null User data array or null if key not found
     */
    public function getUserData(string $userKey): ?array
    {
        return self::buildAvailableUsers()[$userKey] ?? null;
    }

    /**
     * Get all available test user keys.
     *
     * @return array<int, string>
     */
    public function getAvailableUserKeys(): array
    {
        return array_keys(self::buildAvailableUsers());
    }

    /**
     * Check if a test user key exists.
     */
    public function hasUser(string $userKey): bool
    {
        return array_key_exists($userKey, self::buildAvailableUsers());
    }
}
