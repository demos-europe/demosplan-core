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

use DateTime;
use DateTimeZone;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenExpirationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;

class TokenExpirationServiceTest extends TestCase
{
    private const TIMEZONE = 'Europe/Berlin';

    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new TokenExpirationService(
            new NullLogger(),
            $this->createMock(OAuthTokenStorageService::class),
            $this->createMock(OrgaRepository::class),
            $this->createMock(OzgKeycloakSessionManager::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(SecretEncryptor::class),
            self::TIMEZONE,
        );
    }

    // ===== isAccessTokenExpired =====

    public function testAccessTokenExpiredWhenTokenIsNull(): void
    {
        self::assertTrue($this->sut->isAccessTokenExpired(null));
    }

    public function testAccessTokenExpiredWhenExpiryIsNull(): void
    {
        $token = new OAuthToken();

        self::assertTrue($this->sut->isAccessTokenExpired($token));
    }

    public function testAccessTokenExpiredWhenExpiryIsInThePast(): void
    {
        $token = $this->createTokenWithAccessExpiry('-5 minutes');

        self::assertTrue($this->sut->isAccessTokenExpired($token));
    }

    public function testAccessTokenNotExpiredWhenExpiryIsInTheFuture(): void
    {
        $token = $this->createTokenWithAccessExpiry('+5 minutes');

        self::assertFalse($this->sut->isAccessTokenExpired($token));
    }

    // ===== isRefreshTokenExpired =====

    public function testRefreshTokenExpiredWhenTokenIsNull(): void
    {
        self::assertTrue($this->sut->isRefreshTokenExpired(null));
    }

    public function testRefreshTokenExpiredWhenRefreshTokenStringIsNull(): void
    {
        $token = new OAuthToken();

        self::assertTrue($this->sut->isRefreshTokenExpired($token));
    }

    public function testRefreshTokenExpiredWhenExpiryIsNull(): void
    {
        $token = new OAuthToken();
        $token->setRefreshToken('fake-encrypted-refresh-token');

        self::assertTrue($this->sut->isRefreshTokenExpired($token));
    }

    public function testRefreshTokenExpiredWhenExpiryIsInThePast(): void
    {
        $token = $this->createTokenWithRefreshExpiry('-5 minutes');

        self::assertTrue($this->sut->isRefreshTokenExpired($token));
    }

    public function testRefreshTokenNotExpiredWhenExpiryIsInTheFuture(): void
    {
        $token = $this->createTokenWithRefreshExpiry('+30 minutes');

        self::assertFalse($this->sut->isRefreshTokenExpired($token));
    }

    // ===== accessTokenNeedsRefresh =====

    public function testNeedsRefreshWhenTokenIsNull(): void
    {
        self::assertTrue($this->sut->accessTokenNeedsRefresh(null));
    }

    public function testNeedsRefreshWhenExpiryIsNull(): void
    {
        $token = new OAuthToken();

        self::assertTrue($this->sut->accessTokenNeedsRefresh($token));
    }

    public function testNeedsRefreshWhenWithinBufferPeriod(): void
    {
        // Arrange: token expires in 1 minute, buffer is 2 minutes
        $token = $this->createTokenWithAccessExpiry('+1 minute');

        // Assert: needs refresh because 1 min < 2 min buffer
        self::assertTrue($this->sut->accessTokenNeedsRefresh($token, 2));
    }

    public function testDoesNotNeedRefreshWhenOutsideBufferPeriod(): void
    {
        // Arrange: token expires in 10 minutes, buffer is 2 minutes
        $token = $this->createTokenWithAccessExpiry('+10 minutes');

        // Assert: no refresh needed, 10 min > 2 min buffer
        self::assertFalse($this->sut->accessTokenNeedsRefresh($token, 2));
    }

    public function testNeedsRefreshWhenAlreadyExpired(): void
    {
        $token = $this->createTokenWithAccessExpiry('-5 minutes');

        self::assertTrue($this->sut->accessTokenNeedsRefresh($token));
    }

    public function testNeedsRefreshRespectsCustomBufferMinutes(): void
    {
        // Arrange: token expires in 4 minutes
        $token = $this->createTokenWithAccessExpiry('+4 minutes');

        // Assert: 5 min buffer → needs refresh; 2 min buffer → does not
        self::assertTrue($this->sut->accessTokenNeedsRefresh($token, 5));
        self::assertFalse($this->sut->accessTokenNeedsRefresh($token, 2));
    }

    // ===== Helper methods =====

    private function createTokenWithAccessExpiry(string $modifier): OAuthToken
    {
        $token = new OAuthToken();
        $expiry = new DateTime('now', new DateTimeZone(self::TIMEZONE));
        $expiry->modify($modifier);
        $token->setAccessTokenExpiresAt($expiry);

        return $token;
    }

    private function createTokenWithRefreshExpiry(string $modifier): OAuthToken
    {
        $token = new OAuthToken();
        $token->setRefreshToken('fake-encrypted-refresh-token');
        $expiry = new DateTime('now', new DateTimeZone(self::TIMEZONE));
        $expiry->modify($modifier);
        $token->setRefreshTokenExpiresAt($expiry);

        return $token;
    }
}
