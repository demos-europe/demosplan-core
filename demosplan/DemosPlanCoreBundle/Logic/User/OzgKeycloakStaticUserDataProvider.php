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
    /**
     * Hardcoded test users for simulating Keycloak login.
     *
     * Each entry contains JWT-like payload data:
     * - sub: User ID (unique identifier)
     * - preferred_username: Login name
     * - given_name, family_name: User's name
     * - email: Email address
     * - organisation: Array of affiliations (organisational units) with {id, name}
     * - responsibilities: Array of functional areas with {id, name}
     * - organisationId, organisationName: single-org fallback fields (legacy)
     * - resource_access: Keycloak roles using technical codes (FP-A, FP-SB, I-K, I-SB, M-A)
     *
     * Multi-org logic: cartesian product of organisation × responsibilities determines orgs.
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
     * @var array<string, array<string, mixed>>
     */
    final public const AVAILABLE_USERS = [
        // Multi-organisation user: 2 affiliations × 2 responsibilities = 4 orgs (Fachplaner Admin)
        'multi-org-user' => [
            'sub'                => 'keycloak-test-multi-org-001',
            'preferred_username' => 'multi.orga@test.de',
            'given_name'         => 'Multi',
            'family_name'        => 'Orga-Tester',
            'email'              => 'multi.orga@test.de',
            'organisation'       => [
                ['id' => 'TEST.ORGA.ALPHA', 'name' => 'Test Organisation Alpha'],
                ['id' => 'TEST.ORGA.BETA', 'name' => 'Test Organisation Beta'],
            ],
            'responsibilities' => [
                ['id' => 'WATER', 'name' => 'Wasserwirtschaft'],
                ['id' => 'LITTER', 'name' => 'Abfallwirtschaft'],
            ],
            // → 4 orgs: ALPHA.WATER, ALPHA.LITTER, BETA.WATER, BETA.LITTER
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['FP-A', 'FP-SB'],
                ],
            ],
        ],

        // Multi-organisation user: 2 affiliations × 1 responsibility = 2 orgs (TöB Koordinator)
        'dual-org-user' => [
            'sub'                => 'keycloak-test-dual-org-001',
            'preferred_username' => 'dual.orga@test.de',
            'given_name'         => 'Dual',
            'family_name'        => 'Orga-Tester',
            'email'              => 'dual.orga@test.de',
            'organisation'       => [
                ['id' => 'TEST.ORGA.ONE', 'name' => 'Test Organisation Eins'],
                ['id' => 'TEST.ORGA.TWO', 'name' => 'Test Organisation Zwei'],
            ],
            'responsibilities' => [],
            // → 2 orgs from affiliations alone: TEST.ORGA.ONE, TEST.ORGA.TWO
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['I-K', 'I-SB'],
                ],
            ],
        ],

        // Multi-organisation user with organisationId fallback and affiliations (TöB + FP-A)
        'dual-org-user-fpa-toebk' => [
            'sub'                => 'keycloak-test-dual-org-002',
            'preferred_username' => 'dual.orga2@test.de',
            'given_name'         => 'Dual FPA-ToebK',
            'family_name'        => 'Orga-Tester',
            'email'              => 'dual.orga2@test.de',
            'organisationId'     => '123456',
            'organisationName'   => 'KC Organame',
            'organisation'       => [
                ['id' => 'TEST.ORGA.ONE', 'name' => 'Test Organisation Eins'],
                ['id' => 'TEST.ORGA.TWO', 'name' => 'Test Organisation Zwei'],
            ],
            'responsibilities' => [],
            // → 2 orgs from affiliations: TEST.ORGA.ONE, TEST.ORGA.TWO
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['I-K', 'I-SB', 'FP-A'],
                ],
            ],
        ],

        // Single organisation user via organisationId fallback (no arrays)
        'single-org-user' => [
            'sub'                => 'keycloak-test-single-org-001',
            'preferred_username' => 'single.orga@test.de',
            'given_name'         => 'Single',
            'family_name'        => 'Orga-Tester',
            'email'              => 'single.orga@test.de',
            'organisationId'     => 'TEST.ORGA.SINGLE',
            'organisationName'   => 'Test Organisation Single',
            'organisation'       => [],
            'responsibilities'   => [],
            // → 1 org via organisationId fallback: TEST.ORGA.SINGLE
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['FP-A'],
                ],
            ],
        ],

        // Affiliations-only user: single affiliation, no responsibilities
        'affiliations-only-user' => [
            'sub'                => 'keycloak-test-aff-only-001',
            'preferred_username' => 'aff.only@test.de',
            'given_name'         => 'Affiliation',
            'family_name'        => 'Only-Tester',
            'email'              => 'aff.only@test.de',
            'organisation'       => [
                ['id' => 'TEST.ORGA.AFFONLY', 'name' => 'Test Affiliation Only Org'],
            ],
            'responsibilities' => [],
            // → 1 org from affiliation: TEST.ORGA.AFFONLY
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['FP-A'],
                ],
            ],
        ],

        // Fachplaner Admin (planning agency admin) - legacy single-org
        'fachplaner-admin' => [
            'sub'                => 'keycloak-test-fachplaner-001',
            'preferred_username' => 'fachplaner.admin@test.de',
            'given_name'         => 'Fachplaner',
            'family_name'        => 'Admin',
            'email'              => 'fachplaner.admin@test.de',
            'organisationId'     => 'TEST.PLANUNGSBUERO',
            'organisationName'   => 'Test Planungsbüro GmbH',
            'organisation'       => [],
            'responsibilities'   => [],
            'resource_access'    => [
                'dplan-test' => [
                    'roles' => ['FP-A', 'FP-SB'],
                ],
            ],
        ],

        // ToeB Koordinator (public agency coordinator) - legacy single-org
        'toeb-koordinator' => [
            'sub'                => 'keycloak-test-toeb-001',
            'preferred_username' => 'toeb.koordinator@test.de',
            'given_name'         => 'TöB',
            'family_name'        => 'Koordinator',
            'email'              => 'toeb.koordinator@test.de',
            'organisationId'     => 'TEST.BEHOERDE',
            'organisationName'   => 'Test Behörde',
            'organisation'       => [],
            'responsibilities'   => [],
            'resource_access'    => [
                'dplan-test' => [
                    'roles' => ['I-K', 'I-SB'],
                ],
            ],
        ],

        // Private person (citizen)
        'private-person' => [
            'sub'                => 'keycloak-test-citizen-001',
            'preferred_username' => 'max.mustermann@test.de',
            'given_name'         => 'Max',
            'family_name'        => 'Mustermann',
            'email'              => 'max.mustermann@test.de',
            'organisationId'     => '',
            'organisationName'   => '',
            'isPrivatePerson'    => 'true',
            'organisation'       => [],
            'responsibilities'   => [],
            'resource_access'    => [],
        ],

        // Multi-organisation TöB user: 2 affiliations (no responsibilities)
        'multi-toeb' => [
            'sub'                => 'keycloak-test-multi-toeb-001',
            'preferred_username' => 'multi.toeb@test.de',
            'given_name'         => 'Multi',
            'family_name'        => 'TöB-Tester',
            'email'              => 'multi.toeb@test.de',
            'organisation'       => [
                ['id' => 'TEST.BEHOERDE.UMWELT', 'name' => 'Umweltbehörde Test'],
                ['id' => 'TEST.BEHOERDE.BAU', 'name' => 'Baubehörde Test'],
            ],
            'responsibilities' => [],
            // → 2 orgs from affiliations: TEST.BEHOERDE.UMWELT, TEST.BEHOERDE.BAU
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['I-K', 'I-SB'],
                ],
            ],
        ],
    ];

    /**
     * Get user data for a given test user key.
     *
     * @return array<string, mixed>|null User data array or null if key not found
     */
    public function getUserData(string $userKey): ?array
    {
        return self::AVAILABLE_USERS[$userKey] ?? null;
    }

    /**
     * Get all available test user keys.
     *
     * @return array<int, string>
     */
    public function getAvailableUserKeys(): array
    {
        return array_keys(self::AVAILABLE_USERS);
    }

    /**
     * Check if a test user key exists.
     */
    public function hasUser(string $userKey): bool
    {
        return array_key_exists($userKey, self::AVAILABLE_USERS);
    }
}
