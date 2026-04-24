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
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use Tests\Base\UnitTestCase;

class PersonalAccessTokenContextTest extends UnitTestCase
{
    public function testAllowsPermissionIsTrueForScopeImpliedPermission(): void
    {
        $ctx = PersonalAccessTokenContext::fromToken($this->tokenWith([PersonalAccessTokenScope::STATEMENTS_READ]));

        self::assertTrue($ctx->allowsPermission('feature_json_api_list'));
        self::assertTrue($ctx->allowsPermission('feature_json_api_statement'));
    }

    public function testAllowsPermissionIsFalseForPermissionOutsideTheScope(): void
    {
        $ctx = PersonalAccessTokenContext::fromToken($this->tokenWith([PersonalAccessTokenScope::STATEMENTS_READ]));

        self::assertFalse(
            $ctx->allowsPermission('feature_json_api_create'),
            'read-only scope must not imply create permission'
        );
    }

    public function testUnionOfScopesCombinesPermissions(): void
    {
        $ctx = PersonalAccessTokenContext::fromToken($this->tokenWith([
            PersonalAccessTokenScope::STATEMENTS_READ,
            PersonalAccessTokenScope::STATEMENTS_WRITE,
        ]));

        self::assertTrue($ctx->allowsPermission('feature_json_api_list'));
        self::assertTrue($ctx->allowsPermission('feature_json_api_create'));
    }

    public function testHasProcedureRestrictionIsFalseWhenTokenHasNoAllowlist(): void
    {
        $ctx = PersonalAccessTokenContext::fromToken($this->tokenWith(
            [PersonalAccessTokenScope::STATEMENTS_READ],
            procedureIds: null
        ));

        self::assertFalse($ctx->hasProcedureRestriction());
        self::assertTrue($ctx->allowsProcedure('any-procedure-id'));
    }

    public function testAllowsProcedureRespectsAllowlist(): void
    {
        $ctx = PersonalAccessTokenContext::fromToken($this->tokenWith(
            [PersonalAccessTokenScope::STATEMENTS_READ],
            procedureIds: ['proc-1', 'proc-2']
        ));

        self::assertTrue($ctx->hasProcedureRestriction());
        self::assertTrue($ctx->allowsProcedure('proc-1'));
        self::assertFalse($ctx->allowsProcedure('proc-3'));
    }

    /**
     * @param list<string>      $scopes
     * @param list<string>|null $procedureIds
     */
    private function tokenWith(array $scopes, ?array $procedureIds = null): PersonalAccessToken
    {
        return new PersonalAccessToken(
            user: $this->createMock(User::class),
            customer: $this->createMock(Customer::class),
            name: 'test',
            tokenPrefix: 'abcdefghijkl',
            tokenHash: 'hashed',
            scopes: $scopes,
            expiresAt: new DateTime('+30 days'),
            procedureIds: $procedureIds,
        );
    }
}
