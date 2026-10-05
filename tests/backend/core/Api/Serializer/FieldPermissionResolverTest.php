<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Api\Serializer;

use demosplan\DemosPlanCoreBundle\Api\Serializer\FieldPermissionResolver;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Tests\Base\FunctionalTestCase;

class FieldPermissionResolverTest extends FunctionalTestCase
{
    /** Every permission the fixture refers to, so each test starts from a known state. */
    private const FIXTURE_PERMISSIONS = [
        'area_admin_statement_list',
        'area_admin_assessmenttable',
        'feature_json_api_statement',
        'field_statement_memo',
    ];

    protected ?FieldPermissionResolver $sut = null;
    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(FieldPermissionResolver::class);
        $this->user = $this->loginTestUser();
    }

    public function testPropertyWithoutPermissionGroupIsAllowedWithoutAnyPermission(): void
    {
        $this->givenOnlyThesePermissions([]);

        self::assertTrue($this->sut->isPropertyAllowed(PermissionedFixture::class, 'open'));
    }

    public function testUnknownPropertyIsTreatedAsOpen(): void
    {
        $this->givenOnlyThesePermissions([]);

        self::assertTrue($this->sut->isPropertyAllowed(PermissionedFixture::class, 'doesNotExist'));
    }

    public function testSinglePermissionPropertyIsHiddenWithoutThePermission(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);

        self::assertFalse($this->sut->isPropertyAllowed(PermissionedFixture::class, 'single'));
    }

    public function testSinglePermissionPropertyIsAllowedWithThePermission(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_statement_list']);

        self::assertTrue($this->sut->isPropertyAllowed(PermissionedFixture::class, 'single'));
    }

    public function testSeveralGroupsMeanAnyOf(): void
    {
        $this->givenOnlyThesePermissions(['feature_json_api_statement']);

        self::assertTrue($this->sut->isPropertyAllowed(PermissionedFixture::class, 'anyOf'));
    }

    public function testSeveralGroupsAreHiddenWithoutAnyOfThem(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_statement_list']);

        self::assertFalse($this->sut->isPropertyAllowed(PermissionedFixture::class, 'anyOf'));
    }

    public function testPlusInsideOneGroupMeansAllOf(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_assessmenttable', 'field_statement_memo']);

        self::assertTrue($this->sut->isPropertyAllowed(PermissionedFixture::class, 'allOf'));
    }

    public function testPlusInsideOneGroupIsHiddenWhenOnePermissionIsMissing(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_assessmenttable']);

        self::assertFalse($this->sut->isPropertyAllowed(PermissionedFixture::class, 'allOf'));
    }

    public function testGrantedPermissionsContainOnlyThePermissionsTheUserHolds(): void
    {
        $this->givenOnlyThesePermissions(['feature_json_api_statement']);

        $groups = $this->sut->getGrantedPermissions(PermissionedFixture::class);

        self::assertSame(['feature_json_api_statement'], $groups);
    }

    public function testGrantedPermissionsContainACombinedRuleOnlyWhenAllPartsAreHeld(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_assessmenttable', 'field_statement_memo']);

        $groups = $this->sut->getGrantedPermissions(PermissionedFixture::class);

        self::assertEqualsCanonicalizing(
            ['area_admin_assessmenttable', 'area_admin_assessmenttable+field_statement_memo', 'field_statement_memo'],
            $groups
        );
    }

    public function testGroupsThatAreNoPermissionNamesAreNeverGranted(): void
    {
        $this->givenOnlyThesePermissions(self::FIXTURE_PERMISSIONS);

        $groups = $this->sut->getGrantedPermissions(PermissionedFixture::class);

        // "fixture:read" is an ordinary group and "field_statement_memoo" is a typo
        self::assertNotContains('fixture:read', $groups);
        self::assertNotContains('field_statement_memoo', $groups);
    }

    public function testNothingIsGrantedWithoutPermissions(): void
    {
        $this->givenOnlyThesePermissions([]);

        self::assertSame([], $this->sut->getGrantedPermissions(PermissionedFixture::class));
    }

    /**
     * FunctionalTestCase::enablePermissions() re-initialises the permissions on every call, so it
     * cannot be combined with disablePermissions(). This resets, switches all fixture permissions
     * off and enables only the given ones.
     *
     * @param list<string> $enabled
     */
    private function givenOnlyThesePermissions(array $enabled): void
    {
        $permissions = $this->currentUserService->getPermissions();
        $permissions->initPermissions($this->user);
        $permissions->disablePermissions(self::FIXTURE_PERMISSIONS);
        $permissions->enablePermissions($enabled);
    }
}
