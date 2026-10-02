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

use demosplan\DemosPlanCoreBundle\Api\Serializer\PermissionGroupResolver;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Tests\Base\FunctionalTestCase;

class PermissionGroupResolverTest extends FunctionalTestCase
{
    /** Every permission the fixture refers to, so each test starts from a known state. */
    private const FIXTURE_PERMISSIONS = [
        'area_admin_statement_list',
        'area_admin_assessmenttable',
        'feature_json_api_statement',
        'field_statement_memo',
    ];

    protected ?PermissionGroupResolver $sut = null;
    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(PermissionGroupResolver::class);
        $this->user = $this->loginTestUser();
    }

    public function testOpenFieldIsReadableWithoutAnyPermission(): void
    {
        $this->givenOnlyThesePermissions([]);

        self::assertTrue($this->sut->canRead(PermissionedFixture::class, 'open'));
    }

    public function testUnknownPropertyIsTreatedAsOpen(): void
    {
        $this->givenOnlyThesePermissions([]);

        self::assertTrue($this->sut->canRead(PermissionedFixture::class, 'doesNotExist'));
    }

    public function testSinglePermissionFieldIsHiddenWithoutThePermission(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);

        self::assertFalse($this->sut->canRead(PermissionedFixture::class, 'single'));
    }

    public function testSinglePermissionFieldIsReadableWithThePermission(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_statement_list']);

        self::assertTrue($this->sut->canRead(PermissionedFixture::class, 'single'));
    }

    public function testSeveralLabelsMeanAnyOf(): void
    {
        $this->givenOnlyThesePermissions(['feature_json_api_statement']);

        self::assertTrue($this->sut->canRead(PermissionedFixture::class, 'anyOf'));
    }

    public function testSeveralLabelsAreHiddenWithoutAnyOfThem(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_statement_list']);

        self::assertFalse($this->sut->canRead(PermissionedFixture::class, 'anyOf'));
    }

    public function testPlusInsideOneLabelMeansAllOf(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_assessmenttable', 'field_statement_memo']);

        self::assertTrue($this->sut->canRead(PermissionedFixture::class, 'allOf'));
    }

    public function testPlusInsideOneLabelIsHiddenWhenOnePermissionIsMissing(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_assessmenttable']);

        self::assertFalse($this->sut->canRead(PermissionedFixture::class, 'allOf'));
    }

    public function testGrantedGroupsContainOnlyTheLabelsTheUserEarned(): void
    {
        $this->givenOnlyThesePermissions(['feature_json_api_statement']);

        $groups = $this->sut->getGrantedGroups(PermissionedFixture::class, true);

        // the open label (fixture:read) is not a permission label and never added here
        self::assertSame(['perm:read:feature_json_api_statement'], $groups);
    }

    public function testReadAndWriteLabelsAreResolvedSeparately(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);

        $readGroups = $this->sut->getGrantedGroups(PermissionedFixture::class, true);
        $writeGroups = $this->sut->getGrantedGroups(PermissionedFixture::class, false);

        self::assertSame(['perm:read:field_statement_memo'], $readGroups);
        self::assertSame(['perm:write:field_statement_memo'], $writeGroups);
    }

    public function testNoGroupsAreGrantedWithoutPermissions(): void
    {
        $this->givenOnlyThesePermissions([]);

        self::assertSame([], $this->sut->getGrantedGroups(PermissionedFixture::class, true));
        self::assertSame([], $this->sut->getGrantedGroups(PermissionedFixture::class, false));
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
