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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePairingCode;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureIntegrationTokenRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePairingCodeRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenService;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedurePairingCodeService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\Base\UnitTestCase;

/**
 * Drives issue → redeem → authenticate through the real {@see ProcedureIntegrationTokenService} and
 * {@see ApiTokenSecretService}, so a code produces a credential that actually works.
 *
 * The repositories are doubles holding one row each; `consume()` reproduces the conditional UPDATE's
 * outcome. Whether the SQL itself is race-free cannot be shown without a database.
 */
class ProcedurePairingCodeServiceTest extends UnitTestCase
{
    private ?ProcedurePairingCodeService $sut = null;
    private ?MockObject $codeRepository = null;
    private ?ProcedurePairingCode $storedCode = null;
    private ?ProcedureIntegrationToken $storedToken = null;
    private ?ProcedureIntegrationTokenService $tokenService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storedCode = null;
        $this->storedToken = null;

        $this->codeRepository = $this->createMock(ProcedurePairingCodeRepository::class);
        $this->codeRepository->method('persistAndFlush')->willReturnCallback(
            function (ProcedurePairingCode $code): void {
                $this->storedCode = $code;
            }
        );
        $this->codeRepository->method('findByCodeHash')->willReturnCallback(
            fn (string $codeHash): ?ProcedurePairingCode => null !== $this->storedCode
                && hash_equals($this->storedCode->getCodeHash(), $codeHash)
                    ? $this->storedCode
                    : null
        );
        $this->codeRepository->method('consume')->willReturnCallback(
            static function (ProcedurePairingCode $code, DateTime $now): bool {
                if ($code->isConsumed()) {
                    return false;
                }
                $code->markConsumed($now);

                return true;
            }
        );

        $tokenRepository = $this->createMock(ProcedureIntegrationTokenRepository::class);
        $tokenRepository->method('findByPrefix')->willReturnCallback(
            fn (): ?ProcedureIntegrationToken => $this->storedToken
        );
        $tokenRepository->method('persistAndFlush')->willReturnCallback(
            function (ProcedureIntegrationToken $token): void {
                $this->storedToken = $token;
            }
        );

        $this->tokenService = new ProcedureIntegrationTokenService(
            $tokenRepository,
            $this->createMock(EntityManagerInterface::class),
            self::getContainer()->get(ApiTokenSecretService::class),
        );

        $this->sut = new ProcedurePairingCodeService(
            $this->codeRepository,
            $this->tokenService,
            self::getContainer()->get(ApiTokenSecretService::class),
            new NullLogger(),
        );
    }

    public function testIssuesATranscribableCodeAndStoresOnlyItsDigest(): void
    {
        $result = $this->issue();

        self::assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $result->plaintext);
        self::assertStringNotContainsString(
            str_replace('-', '', $result->plaintext),
            $result->code->getCodeHash()
        );
        self::assertSame(64, strlen($result->code->getCodeHash()));
    }

    public function testTheExchangedTokenIsPinnedToTheProcedureAndAuthenticates(): void
    {
        $result = $this->issue();

        $exchanged = $this->sut->redeem($result->plaintext);

        self::assertNotNull($exchanged);
        self::assertSame($result->code->getProcedure(), $exchanged->token->getProcedure());
        self::assertSame($result->code->getName(), $exchanged->token->getName());
        self::assertSame([PersonalAccessTokenScope::RECOMMENDATIONS_WRITE], $exchanged->token->getScopes());
        self::assertSame($exchanged->token, $this->tokenService->findByPlaintext($exchanged->plaintext));
    }

    public function testAcceptsTheCodeInWhateverShapeItComesBackIn(): void
    {
        $result = $this->issue();
        $mangled = ' '.strtoupper(str_replace('-', ' ', $result->plaintext)).' ';

        self::assertNotNull($this->sut->redeem($mangled));
    }

    public function testACodeCanOnlyBeRedeemedOnce(): void
    {
        $result = $this->issue();

        self::assertNotNull($this->sut->redeem($result->plaintext));
        self::assertNull($this->sut->redeem($result->plaintext));
        self::assertTrue($result->code->isConsumed());
    }

    public function testAnExpiredCodeIsNotRedeemable(): void
    {
        $result = $this->issue(ttlMinutes: 1);
        // Reaching into the row rather than sleeping a minute: what is under test is the expiry
        // comparison, not the clock.
        $this->storedCode = $this->expiredCopyOf($result->code);

        self::assertNull($this->sut->redeem($result->plaintext));
    }

    public function testAnUnknownCodeIsNotRedeemable(): void
    {
        $this->issue();

        self::assertNull($this->sut->redeem('abcd-efgh-jkmn'));
    }

    public function testACodeOfTheWrongLengthNeverReachesTheDatabase(): void
    {
        $this->issue();
        $this->codeRepository->expects(self::never())->method('findByCodeHash');

        self::assertNull($this->sut->redeem('too-short'));
    }

    public function testACodeForADeletedProcedureIsNotRedeemable(): void
    {
        $result = $this->issue(procedureDeleted: true);

        self::assertNull($this->sut->redeem($result->plaintext));
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->issue(name: '   ');
    }

    public function testRejectsScopesThatAreAllUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->issue(scopes: ['bogus:scope']);
    }

    public function testRejectsALifetimeThatWouldNeverExpire(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->issue(ttlMinutes: 0);
    }

    public function testRejectsALifetimeBeyondTheAllowedMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->issue(ttlMinutes: 10_000);
    }

    /**
     * @param list<string> $scopes
     */
    private function issue(
        string $name = 'EWM',
        array $scopes = [PersonalAccessTokenScope::RECOMMENDATIONS_WRITE],
        int $ttlMinutes = ProcedurePairingCodeService::DEFAULT_TTL_MINUTES,
        bool $procedureDeleted = false,
    ) {
        return $this->sut->issue(
            procedure: $this->procedure($procedureDeleted),
            customer: $this->createMock(Customer::class),
            name: $name,
            scopes: $scopes,
            ttlMinutes: $ttlMinutes,
        );
    }

    private function procedure(bool $deleted = false): Procedure
    {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn('procedure-1');
        $procedure->method('isDeleted')->willReturn($deleted);

        return $procedure;
    }

    private function expiredCopyOf(ProcedurePairingCode $code): ProcedurePairingCode
    {
        return new ProcedurePairingCode(
            procedure: $code->getProcedure(),
            customer: $code->getCustomer(),
            name: $code->getName(),
            codeHash: $code->getCodeHash(),
            scopes: $code->getScopes(),
            expiresAt: new DateTime('-1 minute'),
        );
    }
}
