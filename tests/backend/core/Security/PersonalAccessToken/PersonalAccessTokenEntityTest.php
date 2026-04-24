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

use DateInterval;
use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Tests\Base\UnitTestCase;

class PersonalAccessTokenEntityTest extends UnitTestCase
{
    public function testIsActiveIsTrueBeforeExpiryAndWithoutRevocation(): void
    {
        $token = $this->buildToken(expiresInDays: 30);

        self::assertTrue($token->isActive(new DateTime()));
        self::assertFalse($token->isExpired(new DateTime()));
        self::assertFalse($token->isRevoked());
    }

    public function testIsExpiredOnceExpiryReached(): void
    {
        $token = $this->buildToken(expiresInDays: -1);

        self::assertTrue($token->isExpired(new DateTime()));
        self::assertFalse($token->isActive(new DateTime()));
    }

    public function testRevokeMarksTokenInactiveAndRecordsRevokerAndTimestamp(): void
    {
        $token = $this->buildToken(expiresInDays: 30);
        $revoker = $this->createMock(User::class);
        $revokedAt = new DateTime('2026-04-01 10:00:00');

        $token->revoke($revokedAt, $revoker);

        self::assertTrue($token->isRevoked());
        self::assertFalse($token->isActive(new DateTime()));
        self::assertSame($revoker, $token->getRevokedBy());
        self::assertEquals($revokedAt, $token->getRevokedAt());
    }

    public function testRevokeIsIdempotent(): void
    {
        $token = $this->buildToken(expiresInDays: 30);
        $firstRevoker = $this->createMock(User::class);
        $secondRevoker = $this->createMock(User::class);
        $firstAt = new DateTime('2026-04-01 10:00:00');
        $secondAt = new DateTime('2026-04-02 10:00:00');

        $token->revoke($firstAt, $firstRevoker);
        $token->revoke($secondAt, $secondRevoker);

        self::assertSame($firstRevoker, $token->getRevokedBy(), 'second revoke must not overwrite first');
        self::assertEquals($firstAt, $token->getRevokedAt());
    }

    public function testMarkUsedUpdatesLastUsedAt(): void
    {
        $token = $this->buildToken(expiresInDays: 30);
        self::assertNull($token->getLastUsedAt());

        $now = new DateTime('2026-04-10 09:00:00');
        $token->markUsed($now);

        self::assertEquals($now, $token->getLastUsedAt());
    }

    public function testScopesAreReturnedAsList(): void
    {
        $token = $this->buildToken(expiresInDays: 30, scopes: ['statements:read', 'procedures:read']);

        self::assertSame(['statements:read', 'procedures:read'], $token->getScopes());
    }

    public function testProcedureIdsAreNullableAndDefaultToNull(): void
    {
        $token = $this->buildToken(expiresInDays: 30);

        self::assertNull($token->getProcedureIds());
    }

    public function testProcedureIdsRetainedWhenProvided(): void
    {
        $ids = ['proc-1', 'proc-2'];
        $token = $this->buildToken(expiresInDays: 30, procedureIds: $ids);

        self::assertSame($ids, $token->getProcedureIds());
    }

    /**
     * @param list<string>      $scopes
     * @param list<string>|null $procedureIds
     */
    private function buildToken(
        int $expiresInDays,
        array $scopes = ['statements:read'],
        ?array $procedureIds = null,
    ): PersonalAccessToken {
        $user = $this->createMock(User::class);
        $customer = $this->createMock(Customer::class);

        $expiresAt = (new DateTime())->add(DateInterval::createFromDateString(($expiresInDays >= 0 ? '+' : '').$expiresInDays.' days'));

        return new PersonalAccessToken(
            user: $user,
            customer: $customer,
            name: 'test-token',
            tokenPrefix: 'abcdefghijkl',
            tokenHash: '$argon2id$dummy',
            scopes: $scopes,
            expiresAt: $expiresAt,
            procedureIds: $procedureIds,
        );
    }
}
