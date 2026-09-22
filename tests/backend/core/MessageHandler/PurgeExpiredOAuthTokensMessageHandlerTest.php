<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use demosplan\DemosPlanCoreBundle\Message\PurgeExpiredOAuthTokensMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\PurgeExpiredOAuthTokensMessageHandler;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PurgeExpiredOAuthTokensMessageHandlerTest extends TestCase
{
    /** @var PurgeExpiredOAuthTokensMessageHandler */
    protected $sut;

    private MockObject&OAuthTokenRepository $oauthTokenRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oauthTokenRepository = $this->createMock(OAuthTokenRepository::class);
        $this->sut = new PurgeExpiredOAuthTokensMessageHandler(
            $this->oauthTokenRepository,
            new NullLogger(),
            'Europe/Berlin',
        );
    }

    public function testInvokeCallsClearOutdated(): void
    {
        // Assert: clearOutdated is called exactly once
        $this->oauthTokenRepository->expects(self::once())->method('clearOutdated')->willReturn(3);

        // Act
        ($this->sut)(new PurgeExpiredOAuthTokensMessage());
    }

    public function testInvokeDoesNotPropagateRepositoryException(): void
    {
        // Arrange: repository throws
        $this->oauthTokenRepository->method('clearOutdated')->willThrowException(new Exception('DB error'));

        // Act: should not propagate
        ($this->sut)(new PurgeExpiredOAuthTokensMessage());

        // Assert: reached here without exception
        self::assertTrue(true);
    }
}
