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
use demosplan\DemosPlanCoreBundle\EventSubscriber\LoginAuditSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\LoginAuditWriter;
use LogicException;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @group UnitTest
 */
class LoginAuditSubscriberTest extends TestCase
{
    public function testSubscribedEventsExcludesTwoFactorSuccessToPreventDuplicateRows(): void
    {
        $events = LoginAuditSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(LoginSuccessEvent::class, $events);
        self::assertArrayHasKey(LoginFailureEvent::class, $events);
        self::assertArrayHasKey(TwoFactorAuthenticationEvents::FAILURE, $events);
        self::assertArrayHasKey(TwoFactorAuthenticationEvents::COMPLETE, $events);
        self::assertArrayNotHasKey(
            TwoFactorAuthenticationEvents::SUCCESS,
            $events,
            'Per-provider SUCCESS must not be subscribed — would cause duplicate audit rows.',
        );
    }

    public function testOnLoginSuccessForwardsUser(): void
    {
        $writer = $this->createMock(LoginAuditWriter::class);
        $sut = new LoginAuditSubscriber($writer);

        $user = $this->createMock(User::class);
        $authenticator = new StubFormAuthenticator();
        $passport = new SelfValidatingPassport(new UserBadge('jane.doe', static fn () => $user));
        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(true);

        $event = new LoginSuccessEvent(
            $authenticator,
            $passport,
            $this->stubToken($user),
            $request,
            null,
            'main',
        );

        $writer->expects(self::once())
            ->method('record')
            ->with(
                LoginAudit::RESULT_SUCCESS,
                $user,
                StubFormAuthenticator::class,
                $request,
            );

        $sut->onLoginSuccess($event);
    }

    public function testOnLoginSuccessSkipsStatelessRequest(): void
    {
        $writer = $this->createMock(LoginAuditWriter::class);
        $sut = new LoginAuditSubscriber($writer);

        $user = $this->createMock(User::class);
        $authenticator = new StubFormAuthenticator();
        $passport = new SelfValidatingPassport(new UserBadge('jane.doe', static fn () => $user));
        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(false);

        $event = new LoginSuccessEvent(
            $authenticator,
            $passport,
            $this->stubToken($user),
            $request,
            null,
            'api',
        );

        $writer->expects(self::never())->method('record');

        $sut->onLoginSuccess($event);
    }

    public function testOnLoginFailureSkipsStatelessRequest(): void
    {
        $writer = $this->createMock(LoginAuditWriter::class);
        $sut = new LoginAuditSubscriber($writer);

        $authenticator = new StubFormAuthenticator();
        $passport = new SelfValidatingPassport(new UserBadge('attacker'));
        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(false);
        $exception = new BadCredentialsException();

        $event = new LoginFailureEvent($exception, $authenticator, $request, null, 'api', $passport);

        $writer->expects(self::never())->method('record');

        $sut->onLoginFailure($event);
    }

    public function testOnLoginFailureRecordsFailureWithExceptionMessageKey(): void
    {
        $writer = $this->createMock(LoginAuditWriter::class);
        $sut = new LoginAuditSubscriber($writer);

        $authenticator = new StubFormAuthenticator();
        $passport = new SelfValidatingPassport(new UserBadge('attacker'));
        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(true);
        $exception = new BadCredentialsException();

        $event = new LoginFailureEvent($exception, $authenticator, $request, null, 'main', $passport);

        $writer->expects(self::once())
            ->method('record')
            ->with(
                LoginAudit::RESULT_FAILURE,
                null,
                StubFormAuthenticator::class,
                $request,
                $exception->getMessageKey(),
            );

        $sut->onLoginFailure($event);
    }

    public function testOnTwoFactorFailureRecordsFailureRow(): void
    {
        $writer = $this->createMock(LoginAuditWriter::class);
        $sut = new LoginAuditSubscriber($writer);

        $user = $this->createMock(User::class);

        $request = $this->createMock(Request::class);
        $event = new TwoFactorAuthenticationEvent($request, $this->stubToken($user));

        $writer->expects(self::once())
            ->method('record')
            ->with(
                LoginAudit::RESULT_FAILURE,
                $user,
                '2fa',
                $request,
                '2fa_invalid_code',
            );

        $sut->onTwoFactorFailure($event);
    }

    public function testOnTwoFactorCompleteRecordsSuccessRowWithCompleteAuthenticator(): void
    {
        $writer = $this->createMock(LoginAuditWriter::class);
        $sut = new LoginAuditSubscriber($writer);

        $user = $this->createMock(User::class);

        $request = $this->createMock(Request::class);
        $event = new TwoFactorAuthenticationEvent($request, $this->stubToken($user));

        $writer->expects(self::once())
            ->method('record')
            ->with(
                LoginAudit::RESULT_SUCCESS,
                $user,
                '2fa_complete',
                $request,
                null,
            );

        $sut->onTwoFactorComplete($event);
    }

    private function stubToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}

/**
 * Named stub used so `::class` returns a deterministic short name in the assertions.
 */
final class StubFormAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): ?bool
    {
        return null;
    }

    public function authenticate(Request $request): Passport
    {
        throw new LogicException('not used');
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        throw new LogicException('not used');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
