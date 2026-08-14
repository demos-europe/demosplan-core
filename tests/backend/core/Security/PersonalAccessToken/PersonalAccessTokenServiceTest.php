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
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Report\PersonalAccessTokenReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\PersonalAccessTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Tests\Base\UnitTestCase;

class PersonalAccessTokenServiceTest extends UnitTestCase
{
    private ?PersonalAccessTokenService $sut = null;
    private ?MockObject $repository = null;
    private ?MockObject $entityManager = null;
    private ?MockObject $hasher = null;
    private ?MockObject $reportService = null;
    private ?MockObject $reportEntryFactory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(PersonalAccessTokenRepository::class);
        $this->repository->method('findByPrefix')->willReturn(null);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->hasher = $this->createMock(PasswordHasherInterface::class);
        $this->hasher->method('hash')->willReturnCallback(
            static fn (string $raw) => 'hashed:'.$raw
        );

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($this->hasher);

        $this->reportEntryFactory = $this->createMock(PersonalAccessTokenReportEntryFactory::class);
        $this->reportEntryFactory->method('createCreationEntry')->willReturn(new ReportEntry());
        $this->reportEntryFactory->method('createRevocationEntry')->willReturn(new ReportEntry());

        $this->reportService = $this->createMock(ReportService::class);

        $this->sut = new PersonalAccessTokenService(
            $this->repository,
            $this->entityManager,
            new ApiTokenSecretService($factory),
            $this->reportEntryFactory,
            $this->reportService,
            new NullLogger(),
        );
    }

    public function testCreateReturnsPlaintextWithCorrectLiteralPrefix(): void
    {
        $result = $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'my token',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(30),
        );

        self::assertStringStartsWith(PersonalAccessToken::TOKEN_LITERAL_PREFIX, $result->plaintext);
        $bodyLength = strlen($result->plaintext) - strlen(PersonalAccessToken::TOKEN_LITERAL_PREFIX);
        self::assertSame(PersonalAccessToken::TOKEN_PREFIX_LENGTH + 32, $bodyLength);
    }

    public function testCreatePersistsTokenWithHashedSecret(): void
    {
        $this->repository->expects(self::once())->method('persistAndFlush');

        $result = $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'my token',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(30),
        );

        self::assertStringStartsWith('hashed:', $result->token->getTokenHash());
        self::assertNotSame($result->plaintext, $result->token->getTokenHash(), 'secret must not be persisted in clear');
    }

    /**
     * Every other test here mocks the hasher factory, which hides whether `password_hashers` holds
     * an entry matching the token class — the real factory throws when none does. Resolving it
     * from the container is what puts that configuration under test.
     */
    public function testHashRoundTripsThroughTheConfiguredHasher(): void
    {
        $stored = null;
        $repository = $this->createMock(PersonalAccessTokenRepository::class);
        // The prefix-uniqueness check during create() must miss, the lookup during
        // findByPlaintext() must hit, so both answers come from the same holder.
        $repository->method('findByPrefix')->willReturnCallback(
            static function () use (&$stored): ?PersonalAccessToken {
                return $stored;
            }
        );
        $repository->method('persistAndFlush')->willReturnCallback(
            static function (PersonalAccessToken $token) use (&$stored): void {
                $stored = $token;
            }
        );

        $sut = $this->buildService(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            self::getContainer()->get(PasswordHasherFactoryInterface::class),
        );

        $result = $sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'round trip',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(30),
        );

        self::assertNotSame($result->plaintext, $result->token->getTokenHash());
        self::assertSame($result->token, $sut->findByPlaintext($result->plaintext));

        $wrongSecret = PersonalAccessToken::TOKEN_LITERAL_PREFIX
            .$result->token->getTokenPrefix()
            .str_repeat('q', 32);
        self::assertNull($sut->findByPlaintext($wrongSecret));
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: '   ',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(30),
        );
    }

    public function testCreateRejectsEmptyScopes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'my token',
            scopes: [],
            expiresAt: $this->daysFromNow(30),
        );
    }

    public function testCreateRejectsAllUnknownScopes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'my token',
            scopes: ['bogus:scope'],
            expiresAt: $this->daysFromNow(30),
        );
    }

    public function testCreateRejectsExpiryInThePast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'my token',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(-1),
        );
    }

    public function testCreateRejectsExpiryBeyondMaxLifetime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'my token',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(PersonalAccessToken::MAX_LIFETIME_DAYS + 1),
        );
    }

    public function testFindByPlaintextReturnsNullForMalformedInput(): void
    {
        self::assertNull($this->sut->findByPlaintext(''));
        self::assertNull($this->sut->findByPlaintext('not_a_pat'));
        self::assertNull($this->sut->findByPlaintext(PersonalAccessToken::TOKEN_LITERAL_PREFIX.'tooshort'));
    }

    public function testFindByPlaintextReturnsNullWhenPrefixUnknown(): void
    {
        // Repository already returns null by default (see setUp).
        // Constant-time no-op requires the hasher to verify against a dummy hash.
        $this->hasher->method('verify')->willReturn(false);

        $candidate = PersonalAccessToken::TOKEN_LITERAL_PREFIX
            .str_repeat('a', PersonalAccessToken::TOKEN_PREFIX_LENGTH)
            .str_repeat('b', 32);

        self::assertNull($this->sut->findByPlaintext($candidate));
    }

    public function testFindByPlaintextReturnsTokenWhenSecretVerifies(): void
    {
        $prefix = str_repeat('a', PersonalAccessToken::TOKEN_PREFIX_LENGTH);
        $secret = str_repeat('b', 32);
        $candidate = PersonalAccessToken::TOKEN_LITERAL_PREFIX.$prefix.$secret;

        // Token's stored hash must match what the verify callback will compare against.
        $token = $this->existingToken(
            prefix: $prefix,
            expiresAt: $this->daysFromNow(30),
            revokedAt: null,
            tokenHash: 'hashed:'.$secret,
        );

        $repo = $this->createMock(PersonalAccessTokenRepository::class);
        $repo->method('findByPrefix')->willReturn($token);

        $verifyHasher = $this->createMock(PasswordHasherInterface::class);
        $verifyHasher->method('verify')->willReturnCallback(
            static fn (string $hash, string $raw): bool => 'hashed:'.$raw === $hash,
        );
        $verifyHasher->method('hash')->willReturnCallback(static fn (string $raw) => 'hashed:'.$raw);

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($verifyHasher);

        $em = $this->createMock(EntityManagerInterface::class);
        $sut = $this->buildService($repo, $em, $factory);

        self::assertSame($token, $sut->findByPlaintext($candidate));
    }

    public function testFindByPlaintextReturnsNullForRevokedToken(): void
    {
        $prefix = str_repeat('a', PersonalAccessToken::TOKEN_PREFIX_LENGTH);
        $secret = str_repeat('b', 32);
        $candidate = PersonalAccessToken::TOKEN_LITERAL_PREFIX.$prefix.$secret;

        $token = $this->existingToken(
            prefix: $prefix,
            expiresAt: $this->daysFromNow(30),
            revokedAt: new DateTime('-1 hour'),
        );

        $repo = $this->createMock(PersonalAccessTokenRepository::class);
        $repo->method('findByPrefix')->willReturn($token);

        $verifyHasher = $this->createMock(PasswordHasherInterface::class);
        $verifyHasher->method('verify')->willReturn(true);
        $verifyHasher->method('hash')->willReturn('hashed:whatever');

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($verifyHasher);

        $em = $this->createMock(EntityManagerInterface::class);
        $sut = $this->buildService($repo, $em, $factory);

        self::assertNull($sut->findByPlaintext($candidate));
    }

    public function testFindByPlaintextReturnsNullForExpiredToken(): void
    {
        $prefix = str_repeat('a', PersonalAccessToken::TOKEN_PREFIX_LENGTH);
        $secret = str_repeat('b', 32);
        $candidate = PersonalAccessToken::TOKEN_LITERAL_PREFIX.$prefix.$secret;

        $token = $this->existingToken(
            prefix: $prefix,
            expiresAt: $this->daysFromNow(-1),
            revokedAt: null,
        );

        $repo = $this->createMock(PersonalAccessTokenRepository::class);
        $repo->method('findByPrefix')->willReturn($token);

        $verifyHasher = $this->createMock(PasswordHasherInterface::class);
        $verifyHasher->method('verify')->willReturn(true);
        $verifyHasher->method('hash')->willReturn('hashed:whatever');

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($verifyHasher);

        $em = $this->createMock(EntityManagerInterface::class);
        $sut = $this->buildService($repo, $em, $factory);

        self::assertNull($sut->findByPlaintext($candidate));
    }

    public function testRevokeIsIdempotent(): void
    {
        // First revoke flushes once; second revoke is a no-op and must not flush again.
        $this->entityManager->expects(self::once())->method('flush');

        $token = $this->existingToken(
            prefix: str_repeat('z', PersonalAccessToken::TOKEN_PREFIX_LENGTH),
            expiresAt: $this->daysFromNow(30),
            revokedAt: null,
        );

        $this->sut->revoke($token);
        self::assertTrue($token->isRevoked());

        $this->sut->revoke($token);
    }

    public function testCreateWritesAuditEntry(): void
    {
        $this->reportEntryFactory->expects(self::once())->method('createCreationEntry');
        $this->reportService->expects(self::once())->method('persistAndFlushReportEntry');

        $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'audit-test',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(30),
        );
    }

    public function testRevokeWritesAuditEntry(): void
    {
        $this->reportEntryFactory->expects(self::once())->method('createRevocationEntry');
        $this->reportService->expects(self::once())->method('persistAndFlushReportEntry');

        $token = $this->existingToken(
            prefix: str_repeat('y', PersonalAccessToken::TOKEN_PREFIX_LENGTH),
            expiresAt: $this->daysFromNow(30),
            revokedAt: null,
        );
        $this->sut->revoke($token);
    }

    public function testAuditWriteFailureDoesNotPropagate(): void
    {
        $this->reportService->method('persistAndFlushReportEntry')
            ->willThrowException(new \RuntimeException('downstream audit store is offline'));

        // Must not throw — token operation succeeds even when audit fails.
        $result = $this->sut->create(
            user: $this->user(),
            customer: $this->customer(),
            name: 'audit-fail-test',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $this->daysFromNow(30),
        );

        self::assertNotEmpty($result->plaintext);
    }

    private function user(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-1');

        return $user;
    }

    private function customer(): Customer
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('customer-1');

        return $customer;
    }

    private function daysFromNow(int $days): DateTime
    {
        $immutable = $days >= 0
            ? (new DateTimeImmutable())->add(new DateInterval('P'.$days.'D'))
            : (new DateTimeImmutable())->sub(new DateInterval('P'.abs($days).'D'));

        return DateTime::createFromImmutable($immutable);
    }

    private function buildService(
        PersonalAccessTokenRepository $repo,
        EntityManagerInterface $em,
        PasswordHasherFactoryInterface $factory,
    ): PersonalAccessTokenService {
        $reportEntryFactory = $this->createMock(PersonalAccessTokenReportEntryFactory::class);
        $reportEntryFactory->method('createCreationEntry')->willReturn(new ReportEntry());
        $reportEntryFactory->method('createRevocationEntry')->willReturn(new ReportEntry());

        return new PersonalAccessTokenService(
            $repo,
            $em,
            new ApiTokenSecretService($factory),
            $reportEntryFactory,
            $this->createMock(ReportService::class),
            new NullLogger(),
        );
    }

    private function existingToken(
        string $prefix,
        DateTime $expiresAt,
        ?DateTime $revokedAt,
        string $tokenHash = 'hashed:whatever',
    ): PersonalAccessToken {
        $token = new PersonalAccessToken(
            user: $this->user(),
            customer: $this->customer(),
            name: 'existing',
            tokenPrefix: $prefix,
            tokenHash: $tokenHash,
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: $expiresAt,
        );

        if (null !== $revokedAt) {
            $token->revoke($revokedAt);
        }

        return $token;
    }
}
