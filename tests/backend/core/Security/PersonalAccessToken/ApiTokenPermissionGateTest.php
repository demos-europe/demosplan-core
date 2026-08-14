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

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext;
use ReflectionObject;
use Tests\Base\UnitTestCase;

/**
 * The two token kinds gate permissions differently, and the difference is the security boundary:
 * a PAT gates only permissions some scope enumerates, an integration token gates everything.
 *
 * Both permissions used here are *enabled* on the evaluator, standing in for a role that granted
 * them — so every denial below comes from the token gate rather than from a missing grant.
 */
class ApiTokenPermissionGateTest extends UnitTestCase
{
    /**
     * In the scope-gated index because some scope lists it.
     */
    private const INDEXED_PERMISSION = 'feature_json_api_update';

    /**
     * Not listed by any scope, so the indexed gate ignores it entirely.
     */
    private const UNINDEXED_PERMISSION = 'feature_statement_content_changes_view';

    public function testPersonalAccessTokenLetsUnindexedPermissionsThrough(): void
    {
        $sut = $this->permissionsWith($this->patContext([PersonalAccessTokenScope::STATEMENTS_READ]));

        self::assertTrue(
            $sut->hasPermission(self::UNINDEXED_PERMISSION),
            'a PAT must not gate permissions no scope enumerates'
        );
    }

    public function testPersonalAccessTokenDeniesIndexedPermissionOutsideItsScopes(): void
    {
        // statements:read implies list/get, not update.
        $sut = $this->permissionsWith($this->patContext([PersonalAccessTokenScope::STATEMENTS_READ]));

        self::assertFalse($sut->hasPermission(self::INDEXED_PERMISSION));
    }

    public function testIntegrationTokenDeniesUnindexedPermissionDespiteTheRoleGrantingIt(): void
    {
        $sut = $this->permissionsWith(
            $this->integrationContext([PersonalAccessTokenScope::RECOMMENDATIONS_WRITE])
        );

        self::assertFalse(
            $sut->hasPermission(self::UNINDEXED_PERMISSION),
            'deny-by-default is the whole point: an unlisted permission must not ride in on the role'
        );
    }

    public function testIntegrationTokenAllowsWhatItsScopeLists(): void
    {
        $sut = $this->permissionsWith(
            $this->integrationContext([PersonalAccessTokenScope::RECOMMENDATIONS_WRITE]),
            ['feature_statement_recommendation_push']
        );

        self::assertTrue($sut->hasPermission('feature_statement_recommendation_push'));
    }

    public function testIntegrationTokenDeniesEvenIndexedPermissionsItDoesNotList(): void
    {
        $sut = $this->permissionsWith(
            $this->integrationContext([PersonalAccessTokenScope::RECOMMENDATIONS_WRITE])
        );

        self::assertFalse($sut->hasPermission(self::INDEXED_PERMISSION));
    }

    public function testWithoutAnyTokenContextNothingIsGated(): void
    {
        $sut = $this->permissionsWith(null);

        self::assertTrue($sut->hasPermission(self::UNINDEXED_PERMISSION));
        self::assertTrue($sut->hasPermission(self::INDEXED_PERMISSION));
    }

    /**
     * Builds the real evaluator with a controlled permission set: the SUT is never mocked, only its
     * state is fixed, because `initPermissions()` would drag in the whole role and DB machinery.
     *
     * @param list<string> $extraPermissions
     */
    private function permissionsWith(
        ?ApiTokenContextInterface $context,
        array $extraPermissions = [],
    ): Permissions {
        $permissions = self::getContainer()->get(Permissions::class);

        $enabled = [];
        $names = [self::INDEXED_PERMISSION, self::UNINDEXED_PERMISSION, ...$extraPermissions];
        foreach ($names as $name) {
            $permission = Permission::instanceFromArray($name, [
                'label'         => $name,
                'enabled'       => true,
                'expose'        => false,
                'loginRequired' => false,
                'description'   => $name,
            ]);
            $permission->enable();
            $enabled[$name] = $permission;
        }

        $reflection = new ReflectionObject($permissions);

        $permissionsProperty = $reflection->getProperty('permissions');
        $permissionsProperty->setAccessible(true);
        $permissionsProperty->setValue($permissions, $enabled);

        $userProperty = $reflection->getProperty('user');
        $userProperty->setAccessible(true);
        $userProperty->setValue($permissions, $this->createMock(User::class));

        $permissions->setApiTokenContext($context);

        return $permissions;
    }

    /**
     * @param list<string> $scopes
     */
    private function patContext(array $scopes): PersonalAccessTokenContext
    {
        return PersonalAccessTokenContext::fromToken(new PersonalAccessToken(
            user: $this->createMock(User::class),
            customer: $this->createMock(Customer::class),
            name: 'pat',
            tokenPrefix: str_repeat('a', PersonalAccessToken::TOKEN_PREFIX_LENGTH),
            tokenHash: 'hashed:secret',
            scopes: $scopes,
            expiresAt: new DateTime('+30 days'),
        ));
    }

    /**
     * @param list<string> $scopes
     */
    private function integrationContext(array $scopes): ProcedureIntegrationTokenContext
    {
        return ProcedureIntegrationTokenContext::fromToken(new ProcedureIntegrationToken(
            procedure: $this->createMock(Procedure::class),
            customer: $this->createMock(Customer::class),
            name: 'integration',
            tokenPrefix: str_repeat('b', ProcedureIntegrationToken::TOKEN_PREFIX_LENGTH),
            tokenHash: 'hashed:secret',
            scopes: $scopes,
        ));
    }
}
