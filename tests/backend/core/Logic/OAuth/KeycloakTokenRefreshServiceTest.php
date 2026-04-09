<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\OAuth;

use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\KeycloakTokenRefreshService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenExpirationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\TokenData;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

class KeycloakTokenRefreshServiceTest extends TestCase
{
    private const TIMEZONE = 'Europe/Berlin';

    protected $sut;

    private MockObject&TokenExpirationService $tokenExpirationService;
    private MockObject&OzgKeycloakSessionManager $sessionManager;
    private MockObject&OAuthTokenRepository $oauthTokenRepository;
    private MockObject&OAuthTokenStorageService $tokenStorageService;
    private MockObject&ClientRegistry $clientRegistry;
    private LockFactory $lockFactory;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenExpirationService = $this->createMock(TokenExpirationService::class);
        $this->sessionManager = $this->createMock(OzgKeycloakSessionManager::class);
        $this->oauthTokenRepository = $this->createMock(OAuthTokenRepository::class);
        $this->tokenStorageService = $this->createMock(OAuthTokenStorageService::class);
        $this->clientRegistry = $this->createMock(ClientRegistry::class);

        $this->lockFactory = new LockFactory(new InMemoryStore());

        $this->sut = new KeycloakTokenRefreshService(
            $this->clientRegistry,
            $this->lockFactory,
            new NullLogger(),
            $this->oauthTokenRepository,
            $this->tokenStorageService,
            $this->sessionManager,
            $this->tokenExpirationService,
            self::TIMEZONE,
        );

        $this->session = new Session(new MockArraySessionStorage());
    }

    // ===== hasValidTokens =====

    public function testHasValidTokensReturnsTrueWhenAccessTokenNotExpired(): void
    {
        // Arrange
        $oauthToken = $this->createTokenWithUser();
        $this->tokenExpirationService->method('isAccessTokenExpired')->willReturn(false);

        // Act
        $result = $this->sut->hasValidTokens($this->session, $oauthToken);

        // Assert
        self::assertTrue($result);
    }

    public function testHasValidTokensSyncsSessionWhenAccessTokenValid(): void
    {
        // Arrange
        $oauthToken = $this->createTokenWithUser();
        $this->tokenExpirationService->method('isAccessTokenExpired')->willReturn(false);

        // Assert: syncSession is called exactly once
        $this->sessionManager->expects(self::once())->method('syncSession');

        // Act
        $this->sut->hasValidTokens($this->session, $oauthToken);
    }

    public function testHasValidTokensReturnsFalseWhenBothTokensExpired(): void
    {
        // Arrange: access expired, refresh also expired → no refresh possible
        $oauthToken = $this->createTokenWithUser();
        $this->tokenExpirationService->method('isAccessTokenExpired')->willReturn(true);
        $this->tokenExpirationService->method('isRefreshTokenExpired')->willReturn(true);

        // Act
        $result = $this->sut->hasValidTokens($this->session, $oauthToken);

        // Assert
        self::assertFalse($result);
    }

    public function testHasValidTokensDoesNotSyncSessionWhenAccessTokenExpired(): void
    {
        // Arrange
        $oauthToken = $this->createTokenWithUser();
        $this->tokenExpirationService->method('isAccessTokenExpired')->willReturn(true);
        $this->tokenExpirationService->method('isRefreshTokenExpired')->willReturn(true);

        // Assert: syncSession is NOT called when token is expired
        $this->sessionManager->expects(self::never())->method('syncSession');

        // Act
        $this->sut->hasValidTokens($this->session, $oauthToken);
    }

    // ===== tryRefreshTokens =====

    public function testTryRefreshReturnsFalseWhenRefreshTokenExpired(): void
    {
        // Arrange
        $oauthToken = $this->createTokenWithUser();
        $this->tokenExpirationService->method('isRefreshTokenExpired')->willReturn(true);

        // Act
        $result = $this->sut->tryRefreshTokens($oauthToken);

        // Assert
        self::assertFalse($result);
    }

    // ===== refreshTokensForUser =====

    public function testRefreshTokensReturnsTrueOnSuccessfulKeycloakRefresh(): void
    {
        // Arrange
        $this->arrangeSuccessfulKeycloakRefresh();

        // Act
        $result = $this->sut->refreshTokensForUser('test-user-id');

        // Assert
        self::assertTrue($result);
    }

    public function testRefreshTokensStoresNewTokensAfterSuccessfulRefresh(): void
    {
        // Arrange
        $newAccessToken = $this->arrangeSuccessfulKeycloakRefresh();

        // Assert: storeTokens is called with the new access token
        $this->tokenStorageService->expects(self::once())
            ->method('storeTokens')
            ->with('test-user-id', $newAccessToken);

        // Act
        $this->sut->refreshTokensForUser('test-user-id');
    }

    public function testRefreshTokensReturnsFalseWhenNoTokenDataExists(): void
    {
        // Arrange: no token data in storage
        $this->tokenStorageService->method('getClearTokenData')->willReturn(null);

        // Act
        $result = $this->sut->refreshTokensForUser('test-user-id');

        // Assert
        self::assertFalse($result);
    }

    public function testRefreshTokensReturnsFalseWhenRefreshTokenIsNull(): void
    {
        // Arrange: token data exists but refresh token is null
        $tokenData = $this->getMockBuilder(TokenData::class)
            ->addMethods(['getRefreshToken'])
            ->getMock();
        $tokenData->method('getRefreshToken')->willReturn(null);
        $this->tokenStorageService->method('getClearTokenData')->willReturn($tokenData);

        // Act
        $result = $this->sut->refreshTokensForUser('test-user-id');

        // Assert
        self::assertFalse($result);
    }

    public function testRefreshTokensReleasesLockEvenOnException(): void
    {
        // Arrange: track lock acquire calls
        $nonBlockingAcquireCalled = false;
        $blockingAcquireCalled = false;

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::exactly(2))->method('acquire')
            ->willReturnCallback(function (bool $blocking) use (&$nonBlockingAcquireCalled, &$blockingAcquireCalled): bool {
                if (!$blocking) {
                    $nonBlockingAcquireCalled = true;

                    return false; // contested — another process holds the lock
                }

                $blockingAcquireCalled = true;

                return true; // blocking wait succeeds
            });
        $lock->expects(self::once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $tokenData = $this->getMockBuilder(TokenData::class)
            ->addMethods(['getRefreshToken'])
            ->getMock();
        $tokenData->method('getRefreshToken')->willReturn('encrypted-refresh-token');
        $this->tokenStorageService->method('getClearTokenData')->willReturn($tokenData);

        $oauthClient = $this->createMock(OAuth2Client::class);
        $oauthClient->method('refreshAccessToken')->willReturnCallback(function (): never {
            throw new Exception('Connection failed');
        });
        $this->clientRegistry->method('getClient')->willReturn($oauthClient);

        $this->oauthTokenRepository->method('haveTokensBeenRefreshed')->willReturn(false);

        $sut = new KeycloakTokenRefreshService(
            $this->clientRegistry,
            $lockFactory,
            new NullLogger(),
            $this->oauthTokenRepository,
            $this->tokenStorageService,
            $this->sessionManager,
            $this->tokenExpirationService,
            self::TIMEZONE,
        );

        // Act
        $result = $sut->refreshTokensForUser('test-user-id');

        // Assert: exception caught, returns false
        self::assertFalse($result);
        // Assert: non-blocking acquire was attempted first (detected contention)
        self::assertTrue($nonBlockingAcquireCalled);
        // Assert: blocking acquire was called after (waited for lock)
        self::assertTrue($blockingAcquireCalled);
    }

    public function testRefreshTokensSkipsKeycloakCallWhenConcurrentRefreshSucceeded(): void
    {
        // Arrange: simulate lock contention — first acquire(false) returns false, then acquire(true) succeeds
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::exactly(2))->method('acquire')
            ->willReturnCallback(function (bool $blocking): bool {
                if (!$blocking) {
                    return false; // contested — another process holds the lock
                }

                return true; // blocking acquire succeeds after "waiting"
            });
        $lock->expects(self::once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        // Repository says tokens were already refreshed by the concurrent process
        $this->oauthTokenRepository->method('haveTokensBeenRefreshed')->willReturn(true);

        // Keycloak client should NOT be called
        $this->clientRegistry->expects(self::never())->method('getClient');

        $sut = new KeycloakTokenRefreshService(
            $this->clientRegistry,
            $lockFactory,
            new NullLogger(),
            $this->oauthTokenRepository,
            $this->tokenStorageService,
            $this->sessionManager,
            $this->tokenExpirationService,
            self::TIMEZONE,
        );

        // Act
        $result = $sut->refreshTokensForUser('test-user-id');

        // Assert: returns true without calling Keycloak
        self::assertTrue($result);
    }

    // ===== Helper methods =====

    private function createTokenWithUser(): OAuthToken
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('test-user-id');

        $token = new OAuthToken();
        $token->setUser($user);

        return $token;
    }

    private function arrangeSuccessfulKeycloakRefresh(): AccessToken
    {
        $tokenData = $this->getMockBuilder(TokenData::class)
            ->addMethods(['getRefreshToken'])
            ->getMock();
        $tokenData->method('getRefreshToken')->willReturn('encrypted-refresh-token');
        $this->tokenStorageService->method('getClearTokenData')->willReturn($tokenData);

        $newAccessToken = new AccessToken([
            'access_token'  => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires'       => time() + 300,
        ]);

        $oauthClient = $this->createMock(OAuth2Client::class);
        $oauthClient->method('refreshAccessToken')->willReturn($newAccessToken);
        $this->clientRegistry->method('getClient')->willReturn($oauthClient);

        return $newAccessToken;
    }
}
