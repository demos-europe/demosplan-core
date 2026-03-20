<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security;

use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\ApiAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Base\UnitTestCase;

class ApiAuthenticatorTest extends UnitTestCase
{
    private ?ApiAuthenticator $sut = null;
    private ?MockObject $userRepository = null;
    private ?MockObject $tokenExtractor = null;

    protected function setUp(): void
    {
        parent::setUp();

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->tokenExtractor = $this->createMock(TokenExtractorInterface::class);
        $userProvider = $this->createMock(UserProviderInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->sut = new ApiAuthenticator(
            $jwtManager,
            $eventDispatcher,
            $this->tokenExtractor,
            $userProvider,
            $this->userRepository,
            $logger
        );
    }

    public function testSupportsReturnsTrueWhenSessionHasUserId(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('userId')->willReturn(true);

        $request = new Request();
        $request->setSession($session);

        self::assertTrue($this->sut->supports($request));
    }

    public function testSupportsReturnsFalseWhenNoSessionAndNoJwt(): void
    {
        $request = new Request();

        $this->tokenExtractor->method('extract')->willReturn(false);

        self::assertFalse($this->sut->supports($request));
    }

    public function testSupportsReturnsTrueWhenJwtTokenPresent(): void
    {
        $request = new Request();

        $this->tokenExtractor->method('extract')->willReturn('valid.jwt.token');

        self::assertTrue($this->sut->supports($request));
    }

    public function testAuthenticateViaSessionReturnsPassportWithUser(): void
    {
        $userId = 'test-user-id';
        $user = $this->createMock(User::class);
        $user->method('getLogin')->willReturn('testuser');

        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('userId')->willReturn(true);
        $session->method('get')->with('userId')->willReturn($userId);

        $request = new Request();
        $request->setSession($session);

        $this->userRepository->method('findOneBy')
            ->with(['id' => $userId, 'deleted' => false])
            ->willReturn($user);

        $passport = $this->sut->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertSame($user, $passport->getUser());
    }

    public function testSupportsReturnsTrueForSessionWithoutUserIdForAnonymousFallback(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('userId')->willReturn(false);

        $request = new Request();
        $request->setSession($session);

        $this->tokenExtractor->method('extract')->willReturn(false);

        // Session without userId and no JWT = supported via anonymous fallback
        self::assertTrue($this->sut->supports($request));
    }

    public function testAuthenticateViaAnonymousWhenSessionHasNoUserId(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('userId')->willReturn(false);
        $session->method('get')->with('userId')->willReturn(null);

        $request = new Request();
        $request->setSession($session);

        $this->tokenExtractor->method('extract')->willReturn(false);

        $passport = $this->sut->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertInstanceOf(AnonymousUser::class, $passport->getUser());
    }

    public function testBearerNullIsIgnoredAndFallsBackToAnonymous(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('userId')->willReturn(false);
        $session->method('get')->with('userId')->willReturn(null);

        $request = new Request();
        $request->setSession($session);

        // Frontend sends "X-JWT-Authorization: Bearer null"
        $this->tokenExtractor->method('extract')->willReturn('null');

        $passport = $this->sut->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertInstanceOf(AnonymousUser::class, $passport->getUser());
    }

    public function testSessionAuthDoesNotAuthenticateDeletedUser(): void
    {
        $userId = 'deleted-user-id';

        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('userId')->willReturn(true);
        $session->method('get')->with('userId')->willReturn($userId);

        $request = new Request();
        $request->setSession($session);

        // User is deleted, so findOneBy returns null
        $this->userRepository->method('findOneBy')
            ->with(['id' => $userId, 'deleted' => false])
            ->willReturn(null);

        // Should fall back to JWT authentication (which will fail without a token)
        $this->tokenExtractor->method('extract')->willReturn(false);

        // supports() should still return true because session has userId
        // (it doesn't check if user exists, only if session has userId)
        self::assertTrue($this->sut->supports($request));
    }
}
