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
use demosplan\DemosPlanCoreBundle\Api\Serializer\PermissionContextBuilder;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\FunctionalTestCase;

class PermissionContextBuilderTest extends FunctionalTestCase
{
    private const FIXTURE_PERMISSIONS = ['area_admin_statement_list', 'field_statement_memo'];

    protected ?PermissionContextBuilder $sut = null;
    private ?User $user = null;
    /** The context the decorated (original) builder returns */
    private ?FixedContextBuilder $original = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->loginTestUser();
        $this->original = new FixedContextBuilder();
        $this->sut = new PermissionContextBuilder(
            $this->original,
            $this->getContainer()->get(FieldPermissionResolver::class)
        );
    }

    /**
     * API Platform reads an empty group list as "no property is allowed", so a context that had no
     * groups must not get an empty list, otherwise resources without groups lose all attributes.
     */
    public function testNoGroupsKeyIsAddedWhenNothingIsEarned(): void
    {
        $this->givenOnlyThesePermissions([]);
        $this->original->context = ['resource_class' => PermissionedFixture::class];

        $context = $this->sut->createFromRequest(new Request(), true);

        self::assertArrayNotHasKey('groups', $context);
    }

    public function testBaseGroupsStayUntouchedWhenNothingIsEarned(): void
    {
        $this->givenOnlyThesePermissions([]);
        $this->original->context = ['resource_class' => PermissionedFixture::class, 'groups' => ['fixture:read']];

        $context = $this->sut->createFromRequest(new Request(), true);

        self::assertSame(['fixture:read'], $context['groups']);
    }

    public function testEarnedPermissionGroupsAreAppendedToBaseGroups(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);
        $this->original->context = ['resource_class' => PermissionedFixture::class, 'groups' => ['fixture:read']];

        $context = $this->sut->createFromRequest(new Request(), true);

        self::assertSame(['fixture:read', 'field_statement_memo'], $context['groups']);
    }

    public function testInputContextUsesTheGroupsOfTheInputClass(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_statement_list', 'field_statement_memo']);
        $this->original->context = [
            'resource_class' => PermissionedFixture::class,
            'input'          => ['class' => PermissionedOtherFixture::class],
            'groups'         => ['fixture:write'],
        ];

        $context = $this->sut->createFromRequest(new Request(), false);

        // the resource class would also grant field_statement_memo, the input class does not
        self::assertSame(['fixture:write', 'area_admin_statement_list'], $context['groups']);
    }

    public function testOutputContextUsesTheGroupsOfTheOutputClass(): void
    {
        $this->givenOnlyThesePermissions(['area_admin_statement_list', 'field_statement_memo']);
        $this->original->context = [
            'resource_class' => PermissionedFixture::class,
            'output'         => ['class' => PermissionedOtherFixture::class],
            'groups'         => ['fixture:read'],
        ];

        $context = $this->sut->createFromRequest(new Request(), true);

        self::assertSame(['fixture:read', 'area_admin_statement_list'], $context['groups']);
    }

    /**
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
