<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken;

use InvalidArgumentException;

/**
 * Static catalogue of scopes that may be attached to a {@see \demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken}.
 *
 * A scope is a coarse-grained label (e.g. `statements:read`) that resolves to a set of
 * fine-grained feature_* / area_* permissions. When a PAT is used to authenticate a request,
 * the effective permissions for that request are intersected with the union of the scopes'
 * permission sets — a PAT can never grant a permission the owning user does not already hold,
 * and the user's role can never grant a permission the PAT's scopes do not permit.
 *
 * The map here is deliberately minimal: it lists the permissions most directly implicated
 * by the scope's label (primarily feature_json_api_* gates). Any additional permissions the
 * user's role carries remain active as long as at least one scope implies them; any permission
 * not implied by any scope is suppressed for the duration of the PAT-authenticated request.
 */
final class PersonalAccessTokenScope
{
    public const PROCEDURES_READ = 'procedures:read';
    public const PROCEDURES_WRITE = 'procedures:write';
    public const STATEMENTS_READ = 'statements:read';
    public const STATEMENTS_WRITE = 'statements:write';
    public const STATEMENTS_MODERATE = 'statements:moderate';
    public const DOCUMENTS_READ = 'documents:read';
    public const DOCUMENTS_WRITE = 'documents:write';
    public const ORGANISATIONS_READ = 'organisations:read';
    public const MAPS_READ = 'maps:read';
    public const REPORTS_READ = 'reports:read';
    public const ADMIN_CONTENT = 'admin:content';
    public const ADMIN_USERS = 'admin:users';
    /**
     * Narrower than {@see self::STATEMENTS_WRITE}, which also implies create, update and the
     * statement/segment resource permissions. This one implies exactly one permission, so a token
     * carrying it can write recommendations and do nothing else.
     */
    public const RECOMMENDATIONS_WRITE = 'recommendations:write';

    private const READ_BASE = [
        'feature_json_api_list',
        'feature_json_api_get',
    ];

    private const WRITE_BASE = [
        'feature_json_api_create',
        'feature_json_api_update',
        'feature_json_api_delete',
    ];

    /**
     * Scope → list of permissions that the scope implies.
     * Extend carefully: adding a permission here means any existing PAT with that scope
     * gains access to the new permission on its next request.
     *
     * @var array<string, list<string>>
     */
    private const SCOPE_PERMISSIONS = [
        self::RECOMMENDATIONS_WRITE => [
            'feature_statement_recommendation_push',
        ],
        self::PROCEDURES_READ => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_procedure',
            'feature_procedure_api_access',
        ],
        self::PROCEDURES_WRITE => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_create',
            'feature_json_api_update',
            'feature_json_api_delete',
            'feature_json_api_procedure',
            'feature_procedure_api_access',
        ],
        self::STATEMENTS_READ => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_statement',
            'feature_json_api_original_statement',
            'feature_json_api_statement_segment',
        ],
        self::STATEMENTS_WRITE => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_create',
            'feature_json_api_update',
            'feature_json_api_statement',
            'feature_json_api_original_statement',
            'feature_json_api_statement_segment',
        ],
        self::STATEMENTS_MODERATE => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_update',
            'feature_json_api_statement',
            'feature_json_api_statement_segment',
            'feature_json_api_tag',
            'feature_json_api_tag_create',
            'feature_json_api_tag_topic',
            'feature_json_api_tag_topic_create',
        ],
        self::DOCUMENTS_READ => [
            'feature_json_api_list',
            'feature_json_api_get',
        ],
        self::DOCUMENTS_WRITE => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_create',
            'feature_json_api_update',
            'feature_json_api_delete',
        ],
        self::ORGANISATIONS_READ => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_user',
            'feature_json_api_user_role_in_customer',
        ],
        self::MAPS_READ => [
            'feature_json_api_list',
            'feature_json_api_get',
        ],
        self::REPORTS_READ => [
            'feature_json_api_list',
            'feature_json_api_get',
        ],
        self::ADMIN_CONTENT => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_create',
            'feature_json_api_update',
            'feature_json_api_delete',
            'feature_json_api_tag',
            'feature_json_api_tag_create',
            'feature_json_api_tag_topic',
            'feature_json_api_tag_topic_create',
        ],
        self::ADMIN_USERS => [
            'feature_json_api_list',
            'feature_json_api_get',
            'feature_json_api_create',
            'feature_json_api_update',
            'feature_json_api_user',
            'feature_json_api_user_role_in_customer',
        ],
    ];

    /**
     * Human-facing labels in German (dplan UI language). Kept next to the constants
     * rather than in translation keys because scope names are part of the API contract
     * and referenced verbatim by token creators.
     *
     * @var array<string, string>
     */
    private const SCOPE_LABELS = [
        self::PROCEDURES_READ => 'Verfahren lesen',
        self::PROCEDURES_WRITE => 'Verfahren bearbeiten',
        self::STATEMENTS_READ => 'Stellungnahmen lesen',
        self::STATEMENTS_WRITE => 'Stellungnahmen einreichen/bearbeiten',
        self::STATEMENTS_MODERATE => 'Stellungnahmen moderieren (Status, Tags, Segmente)',
        self::DOCUMENTS_READ => 'Dokumente lesen',
        self::DOCUMENTS_WRITE => 'Dokumente hochladen/bearbeiten',
        self::ORGANISATIONS_READ => 'Organisationen und Nutzer lesen',
        self::MAPS_READ => 'Kartendaten lesen',
        self::REPORTS_READ => 'Berichte lesen',
        self::ADMIN_CONTENT => 'Inhaltsverwaltung (Textbausteine, FAQ, News, Tags)',
        self::ADMIN_USERS => 'Benutzer- und Organisationsverwaltung',
        self::RECOMMENDATIONS_WRITE => 'Empfehlungen einer anderen Instanz übernehmen',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::SCOPE_PERMISSIONS);
    }

    public static function exists(string $scope): bool
    {
        return array_key_exists($scope, self::SCOPE_PERMISSIONS);
    }

    public static function labelFor(string $scope): string
    {
        self::assertExists($scope);

        return self::SCOPE_LABELS[$scope];
    }

    /** @return list<string> */
    public static function permissionsFor(string $scope): array
    {
        self::assertExists($scope);

        return self::SCOPE_PERMISSIONS[$scope];
    }

    /**
     * Union of the permissions implied by the given scopes.
     *
     * @param list<string> $scopes
     *
     * @return list<string>
     */
    public static function unionPermissions(array $scopes): array
    {
        $permissions = [];
        foreach ($scopes as $scope) {
            if (!self::exists($scope)) {
                continue;
            }
            foreach (self::SCOPE_PERMISSIONS[$scope] as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }

    /**
     * Returns only the valid scope identifiers from the input, preserving order and removing duplicates.
     *
     * @param list<string> $scopes
     *
     * @return list<string>
     */
    public static function filterValid(array $scopes): array
    {
        $seen = [];
        $result = [];
        foreach ($scopes as $scope) {
            if (!self::exists($scope) || isset($seen[$scope])) {
                continue;
            }
            $seen[$scope] = true;
            $result[] = $scope;
        }

        return $result;
    }

    /** @return array<string, array{label: string, permissions: list<string>}> */
    public static function toArray(): array
    {
        $out = [];
        foreach (self::SCOPE_PERMISSIONS as $scope => $permissions) {
            $out[$scope] = [
                'label'       => self::SCOPE_LABELS[$scope],
                'permissions' => $permissions,
            ];
        }

        return $out;
    }

    private static function assertExists(string $scope): void
    {
        if (!self::exists($scope)) {
            throw new InvalidArgumentException(sprintf('Unknown PAT scope "%s".', $scope));
        }
    }
}
