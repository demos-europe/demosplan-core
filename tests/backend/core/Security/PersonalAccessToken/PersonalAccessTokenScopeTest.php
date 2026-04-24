<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security\PersonalAccessToken;

use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use InvalidArgumentException;
use Tests\Base\UnitTestCase;

class PersonalAccessTokenScopeTest extends UnitTestCase
{
    public function testAllReturnsEveryDeclaredScope(): void
    {
        $all = PersonalAccessTokenScope::all();

        self::assertNotEmpty($all);
        self::assertContains(PersonalAccessTokenScope::STATEMENTS_READ, $all);
        self::assertContains(PersonalAccessTokenScope::STATEMENTS_WRITE, $all);
        self::assertContains(PersonalAccessTokenScope::ADMIN_USERS, $all);
    }

    public function testExistsIsTrueForKnownScope(): void
    {
        self::assertTrue(PersonalAccessTokenScope::exists(PersonalAccessTokenScope::STATEMENTS_READ));
        self::assertFalse(PersonalAccessTokenScope::exists('not:a:real:scope'));
    }

    public function testLabelForThrowsForUnknownScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PersonalAccessTokenScope::labelFor('not:a:real:scope');
    }

    public function testPermissionsForStatementsReadImpliesJsonApiListAndStatementResource(): void
    {
        $permissions = PersonalAccessTokenScope::permissionsFor(PersonalAccessTokenScope::STATEMENTS_READ);

        self::assertContains('feature_json_api_list', $permissions);
        self::assertContains('feature_json_api_get', $permissions);
        self::assertContains('feature_json_api_statement', $permissions);
        self::assertNotContains('feature_json_api_create', $permissions, 'read scope must not imply write-side permissions');
        self::assertNotContains('feature_json_api_delete', $permissions);
    }

    public function testFilterValidDropsUnknownAndDeduplicates(): void
    {
        $result = PersonalAccessTokenScope::filterValid([
            PersonalAccessTokenScope::STATEMENTS_READ,
            'bogus:scope',
            PersonalAccessTokenScope::STATEMENTS_READ,
            PersonalAccessTokenScope::PROCEDURES_READ,
        ]);

        self::assertSame([
            PersonalAccessTokenScope::STATEMENTS_READ,
            PersonalAccessTokenScope::PROCEDURES_READ,
        ], $result);
    }

    public function testUnionPermissionsCombinesWithoutDuplicates(): void
    {
        $union = PersonalAccessTokenScope::unionPermissions([
            PersonalAccessTokenScope::STATEMENTS_READ,
            PersonalAccessTokenScope::STATEMENTS_WRITE,
        ]);

        self::assertSame(count($union), count(array_unique($union)), 'union must not contain duplicates');
        self::assertContains('feature_json_api_list', $union);
        self::assertContains('feature_json_api_create', $union);
    }

    public function testUnionPermissionsIgnoresUnknownScopes(): void
    {
        $union = PersonalAccessTokenScope::unionPermissions(['bogus:scope']);

        self::assertSame([], $union);
    }

    public function testToArrayContainsLabelAndPermissionsForEachScope(): void
    {
        $array = PersonalAccessTokenScope::toArray();

        self::assertSame(PersonalAccessTokenScope::all(), array_keys($array));
        foreach ($array as $scope => $entry) {
            self::assertArrayHasKey('label', $entry, $scope);
            self::assertArrayHasKey('permissions', $entry, $scope);
            self::assertIsString($entry['label'], $scope);
            self::assertIsArray($entry['permissions'], $scope);
            self::assertNotEmpty($entry['permissions'], $scope);
        }
    }
}
