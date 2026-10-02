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
use demosplan\DemosPlanCoreBundle\Api\Serializer\PermissionGroupsContextBuilder;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\FunctionalTestCase;

class PermissionGroupsContextBuilderTest extends FunctionalTestCase
{
    protected ?PermissionGroupsContextBuilder $sut = null;
    private ?User $user = null;
    /** The context the decorated (original) builder returns */
    private ?FixedContextBuilder $original = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->loginTestUser();
        $this->original = new FixedContextBuilder();
        $this->sut = new PermissionGroupsContextBuilder(
            $this->original,
            $this->getContainer()->get(PermissionGroupResolver::class)
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

    public function testEarnedLabelsAreAppendedToBaseGroups(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);
        $this->original->context = ['resource_class' => PermissionedFixture::class, 'groups' => ['fixture:read']];

        $context = $this->sut->createFromRequest(new Request(), true);

        self::assertSame(['fixture:read', 'perm:read:field_statement_memo'], $context['groups']);
    }

    public function testInputContextGetsTheWriteLabels(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);
        $this->original->context = ['resource_class' => PermissionedFixture::class, 'groups' => ['fixture:write']];

        $context = $this->sut->createFromRequest(new Request(), false);

        self::assertSame(['fixture:write', 'perm:write:field_statement_memo'], $context['groups']);
    }

    public function testContextWithoutResourceClassIsReturnedUntouched(): void
    {
        $this->givenOnlyThesePermissions(['field_statement_memo']);
        $this->original->context = ['groups' => ['something']];

        $context = $this->sut->createFromRequest(new Request(), true);

        self::assertSame(['something'], $context['groups']);
    }

    /**
     * @param list<string> $enabled
     */
    private function givenOnlyThesePermissions(array $enabled): void
    {
        $permissions = $this->currentUserService->getPermissions();
        $permissions->initPermissions($this->user);
        $permissions->disablePermissions(['field_statement_memo']);
        $permissions->enablePermissions($enabled);
    }
}
