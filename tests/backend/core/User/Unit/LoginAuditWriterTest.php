<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use demosplan\DemosPlanCoreBundle\Entity\User\LoginAudit;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\LoginAuditWriter;
use demosplan\DemosPlanCoreBundle\Repository\LoginAuditRepository;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group UnitTest
 */
class LoginAuditWriterTest extends TestCase
{
    public function testRecordPersistsExpectedEntity(): void
    {
        $repository = $this->createMock(LoginAuditRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-uuid-1');

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->method('hasSession')->willReturn(false);

        /** @var LoginAudit|null $captured */
        $captured = null;
        $repository->expects(self::once())
            ->method('persistAndFlush')
            ->willReturnCallback(function (LoginAudit $audit) use (&$captured): void {
                $captured = $audit;
            });

        $logger->expects(self::never())->method('critical');

        $sut = new LoginAuditWriter($repository, $logger);
        $sut->record(
            LoginAudit::RESULT_SUCCESS,
            $user,
            'LoginFormAuthenticator',
            $request,
        );

        self::assertNotNull($captured);
        self::assertSame(LoginAudit::RESULT_SUCCESS, $captured->getResult());
        self::assertSame('LoginFormAuthenticator', $captured->getAuthenticator());
        self::assertSame('Mozilla/5.0', $captured->getUserAgent());
        self::assertSame('user-uuid-1', $captured->getUserId());
        self::assertNull($captured->getFailureReason());
        self::assertNull($captured->getSessionIdHash());
    }

    public function testRecordSwallowsRepositoryFailureAndLogsCritical(): void
    {
        $repository = $this->createMock(LoginAuditRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repository->method('persistAndFlush')->willThrowException(new Exception('db down'));

        $logger->expects(self::once())
            ->method('critical')
            ->with(
                self::stringContains('Failed to persist login audit row'),
                self::callback(fn (array $ctx) => 'failure' === $ctx['result']
                    && 'LoginFormAuthenticator' === $ctx['authenticator']),
            );

        $sut = new LoginAuditWriter($repository, $logger);

        $sut->record(
            LoginAudit::RESULT_FAILURE,
            null,
            'LoginFormAuthenticator',
            null,
            'invalid_credentials',
        );

        self::assertTrue(true);
    }

    public function testRecordSkipsSuccessWhenDuplicateExistsForSession(): void
    {
        $repository = $this->createMock(LoginAuditRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([]);
        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\SessionInterface::class);
        $session->method('isStarted')->willReturn(true);
        $session->method('getId')->willReturn('sess-id-abc');
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        $repository->expects(self::once())
            ->method('existsSuccessForSessionAndAuthenticator')
            ->with(hash('sha256', 'sess-id-abc'), 'LoginFormAuthenticator')
            ->willReturn(true);

        $repository->expects(self::never())->method('persistAndFlush');

        $sut = new LoginAuditWriter($repository, $logger);
        $sut->record(
            LoginAudit::RESULT_SUCCESS,
            null,
            'LoginFormAuthenticator',
            $request,
        );
    }

    public function testRecordAlwaysPersistsFailureEvenWhenDuplicateExists(): void
    {
        $repository = $this->createMock(LoginAuditRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([]);
        $request->method('hasSession')->willReturn(false);

        $repository->expects(self::never())->method('existsSuccessForSessionAndAuthenticator');
        $repository->expects(self::once())->method('persistAndFlush');

        $sut = new LoginAuditWriter($repository, $logger);
        $sut->record(
            LoginAudit::RESULT_FAILURE,
            null,
            '2fa',
            $request,
            '2fa_invalid_code',
        );
    }

    public function testRecordTruncatesOversizedFields(): void
    {
        $repository = $this->createMock(LoginAuditRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $longUserAgent = str_repeat('b', 600);

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => $longUserAgent]);
        $request->method('hasSession')->willReturn(false);

        /** @var LoginAudit|null $captured */
        $captured = null;
        $repository->expects(self::once())
            ->method('persistAndFlush')
            ->willReturnCallback(function (LoginAudit $audit) use (&$captured): void {
                $captured = $audit;
            });

        $sut = new LoginAuditWriter($repository, $logger);
        $sut->record(
            LoginAudit::RESULT_FAILURE,
            null,
            'LoginFormAuthenticator',
            $request,
            str_repeat('c', 300),
        );

        self::assertNotNull($captured);
        self::assertSame(512, mb_strlen($captured->getUserAgent()));
        self::assertSame(255, mb_strlen($captured->getFailureReason()));
    }
}
