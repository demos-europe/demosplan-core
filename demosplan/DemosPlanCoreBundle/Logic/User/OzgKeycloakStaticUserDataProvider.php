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
     * - organisationId, organisationName: single-org fields
     * - responsibilities: Array of multiple responsibilities for multi-org users
     * - resource_access: Keycloak roles using technical codes (FP-A, FP-SB, I-K, I-SB, M-A)
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
        // Multi-responsibility user with 3 organisations (Fachplaner Admin)
        'multi-org-user' => [
            'sub' => 'keycloak-test-multi-org-001',
            'preferred_username' => 'multi.orga@test.de',
            'given_name' => 'Multi',
            'family_name' => 'Orga-Tester',
            'email' => 'multi.orga@test.de',
            'organisationId' => '',  // Empty for multi-org - responsibilities take precedence
            'organisationName' => '',
            'responsibilities' => [
                [
                    'responsibility' => 'TEST.ORGA.ALPHA',
                    'orgaName' => 'Test Organisation Alpha',
                ],
                [
                    'responsibility' => 'TEST.ORGA.BETA',
                    'orgaName' => 'Test Organisation Beta',
                ],
                [
                    'responsibility' => 'TEST.ORGA.GAMMA',
                    'orgaName' => 'Test Organisation Gamma',
                ],
            ],
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['FP-A', 'FP-SB'],
                ],
            ],
        ],

        // Multi-responsibility user with 2 organisations (TöB Koordinator)
        'dual-org-user' => [
            'sub' => 'keycloak-test-dual-org-001',
            'preferred_username' => 'dual.orga@test.de',
            'given_name' => 'Dual',
            'family_name' => 'Orga-Tester',
            'email' => 'dual.orga@test.de',
            'organisationId' => '',
            'organisationName' => '',
            'responsibilities' => [
                [
                    'responsibility' => 'TEST.ORGA.ONE',
                    'orgaName' => 'Test Organisation Eins',
                ],
                [
                    'responsibility' => 'TEST.ORGA.TWO',
                    'orgaName' => 'Test Organisation Zwei',
                ],
            ],
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['I-K', 'I-SB'],
                ],
            ],
        ],

        // Single organisation user
        'single-org-user' => [
            'sub' => 'keycloak-test-single-org-001',
            'preferred_username' => 'single.orga@test.de',
            'given_name' => 'Single',
            'family_name' => 'Orga-Tester',
            'email' => 'single.orga@test.de',
            'organisationId' => 'TEST.ORGA.SINGLE',
            'organisationName' => 'Test Organisation Single',
            'responsibilities' => [],  // Empty - uses organisationId field
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['FP-A'],
                ],
            ],
        ],

        // Fachplaner Admin (planning agency admin)
        'fachplaner-admin' => [
            'sub' => 'keycloak-test-fachplaner-001',
            'preferred_username' => 'fachplaner.admin@test.de',
            'given_name' => 'Fachplaner',
            'family_name' => 'Admin',
            'email' => 'fachplaner.admin@test.de',
            'organisationId' => 'TEST.PLANUNGSBUERO',
            'organisationName' => 'Test Planungsbüro GmbH',
            'responsibilities' => [],
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['FP-A', 'FP-SB'],
                ],
            ],
        ],

        // ToeB Koordinator (public agency coordinator)
        'toeb-koordinator' => [
            'sub' => 'keycloak-test-toeb-001',
            'preferred_username' => 'toeb.koordinator@test.de',
            'given_name' => 'TöB',
            'family_name' => 'Koordinator',
            'email' => 'toeb.koordinator@test.de',
            'organisationId' => 'TEST.BEHOERDE',
            'organisationName' => 'Test Behörde',
            'responsibilities' => [],
            'resource_access' => [
                'dplan-test' => [
                    'roles' => ['I-K', 'I-SB'],
                ],
            ],
        ],

        // Private person (citizen)
        'private-person' => [
            'sub' => 'keycloak-test-citizen-001',
            'preferred_username' => 'max.mustermann@test.de',
            'given_name' => 'Max',
            'family_name' => 'Mustermann',
            'email' => 'max.mustermann@test.de',
            'organisationId' => '',
            'organisationName' => '',
            'isPrivatePerson' => 'true',
            'responsibilities' => [],
            'resource_access' => [],
        ],

        // Multi-responsibility TöB user
        'multi-toeb' => [
            'sub' => 'keycloak-test-multi-toeb-001',
            'preferred_username' => 'multi.toeb@test.de',
            'given_name' => 'Multi',
            'family_name' => 'TöB-Tester',
            'email' => 'multi.toeb@test.de',
            'organisationId' => '',
            'organisationName' => '',
            'responsibilities' => [
                [
                    'responsibility' => 'TEST.BEHOERDE.UMWELT',
                    'orgaName' => 'Umweltbehörde Test',
                ],
                [
                    'responsibility' => 'TEST.BEHOERDE.BAU',
                    'orgaName' => 'Baubehörde Test',
                ],
            ],
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
