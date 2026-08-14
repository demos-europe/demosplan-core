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
use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Tests\Base\UnitTestCase;

class ProcedureIntegrationTokenEntityTest extends UnitTestCase
{
    private const PROCEDURE_ID = 'procedure-1';

    public function testWithoutExpiryStaysActiveIndefinitely(): void
    {
        $sut = $this->token();

        self::assertNull($sut->getExpiresAt());
        self::assertFalse($sut->isExpired(new DateTime('+100 years')));
        self::assertTrue($sut->isActive(new DateTime('+100 years')));
    }

    public function testExpiresWhenGivenAnExpiryDate(): void
    {
        $sut = $this->token($this->daysFromNow(-1));

        self::assertTrue($sut->isExpired(new DateTime()));
        self::assertFalse($sut->isActive(new DateTime()));
    }

    public function testRevocationEndsAccessEvenWithoutExpiry(): void
    {
        $sut = $this->token();
        $revoker = $this->createMock(User::class);

        $sut->revoke(new DateTime(), $revoker);

        self::assertTrue($sut->isRevoked());
        self::assertFalse($sut->isActive(new DateTime()));
        self::assertSame($revoker, $sut->getRevokedBy());
    }

    public function testAllowsOnlyItsOwnProcedure(): void
    {
        $sut = $this->token();

        self::assertTrue($sut->allowsProcedure(self::PROCEDURE_ID));
        self::assertFalse($sut->allowsProcedure('some-other-procedure'));
    }

    /**
     * The two kinds must never be mistaken for one another in a log line or a lookup.
     */
    public function testLiteralPrefixDiffersFromPersonalAccessToken(): void
    {
        self::assertNotSame(
            PersonalAccessToken::TOKEN_LITERAL_PREFIX,
            ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX
        );
        self::assertSame(
            ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX,
            ProcedureIntegrationToken::literalPrefix()
        );
    }

    /**
     * The integration outlives the planner who paired it, so the creator is optional.
     */
    public function testCreatedByIsOptional(): void
    {
        self::assertNull($this->token()->getCreatedBy());
    }

    private function token(?DateTime $expiresAt = null): ProcedureIntegrationToken
    {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn(self::PROCEDURE_ID);

        return new ProcedureIntegrationToken(
            procedure: $procedure,
            customer: $this->createMock(Customer::class),
            name: 'ewm integration',
            tokenPrefix: str_repeat('a', ProcedureIntegrationToken::TOKEN_PREFIX_LENGTH),
            tokenHash: 'hashed:secret',
            scopes: ['statements:write'],
            expiresAt: $expiresAt,
        );
    }

    private function daysFromNow(int $days): DateTime
    {
        $immutable = $days >= 0
            ? (new DateTimeImmutable())->add(new DateInterval('P'.$days.'D'))
            : (new DateTimeImmutable())->sub(new DateInterval('P'.abs($days).'D'));

        return DateTime::createFromImmutable($immutable);
    }
}
