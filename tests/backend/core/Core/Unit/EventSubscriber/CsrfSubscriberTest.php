<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\EventSubscriber\CsrfSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Tests for the CsrfSubscriber with focus on token sanitization.
 */
class CsrfSubscriberTest extends TestCase
{
    private const TEST_URL = '/test';
    private const VALID_TOKEN_VALUE = 'valid-token-1234';
    private const MALICIOUS_TOKEN = "valid-token-1234\r\nX-Malicious: exploit";
    private const SCRIPT_TOKEN = 'valid-token-<script>alert(1)</script>';

    private CsrfSubscriber $subscriber;
    private MockObject $csrfTokenManager;
    private MockObject $messageBag;
    private MockObject $logger;
    private HeaderSanitizerService $headerSanitizer;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->headerSanitizer = new HeaderSanitizerService();

        $this->subscriber = new CsrfSubscriber(
            $this->csrfTokenManager,
            $this->messageBag,
            $this->logger,
            $this->headerSanitizer
        );
    }

    /**
     * Test that GET requests are ignored.
     */
    public function testGetRequestsAreIgnored(): void
    {
        $request = Request::create(self::TEST_URL);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        $this->csrfTokenManager->expects($this->never())->method('getToken');
        $this->csrfTokenManager->expects($this->never())->method('isTokenValid');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that standard CSRF token is handled correctly.
     */
    public function testStandardCsrfToken(): void
    {
        $request = Request::create(self::TEST_URL, 'POST');
        $request->headers->set('x-csrf-token', self::VALID_TOKEN_VALUE);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t) => CsrfSubscriber::TOKEN_ID === $t->getId()
                && self::VALID_TOKEN_VALUE === $t->getValue()))
            ->willReturn(true);

        $this->messageBag->expects($this->never())->method('add');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that a malicious CSRF token is properly sanitized before validation.
     */
    public function testMaliciousCsrfToken(): void
    {
        $sanitizedToken = $this->headerSanitizer->sanitizeCsrfToken(self::MALICIOUS_TOKEN);

        // Sanity-check that sanitization actually removed the malicious part.
        $this->assertEquals(self::VALID_TOKEN_VALUE, $sanitizedToken);

        $request = Request::create(self::TEST_URL, 'POST');
        $request->headers->set('x-csrf-token', self::MALICIOUS_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t) => CsrfSubscriber::TOKEN_ID === $t->getId()
                && $sanitizedToken === $t->getValue()))
            ->willReturn(true);

        $this->messageBag->expects($this->never())->method('add');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that a token with script tags is properly sanitized.
     */
    public function testTokenWithScriptTags(): void
    {
        $sanitizedToken = $this->headerSanitizer->sanitizeCsrfToken(self::SCRIPT_TOKEN);

        $request = Request::create(self::TEST_URL, 'POST');
        $request->headers->set('x-csrf-token', self::SCRIPT_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t) => CsrfSubscriber::TOKEN_ID === $t->getId()
                && $sanitizedToken === $t->getValue()))
            ->willReturn(true);

        $this->messageBag->expects($this->never())->method('add');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Regression test for the bug where any non-empty client-supplied string
     * was accepted: the subscriber used to call getToken($clientValue), which
     * lazily creates a token under that id and then validates it against
     * itself. The fix wires isTokenValid directly with the well-known id.
     */
    public function testInvalidTokenIsRejectedAndGetTokenIsNeverCalled(): void
    {
        $request = Request::create(self::TEST_URL, 'POST', ['_token' => 'attacker-supplied-value']);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        $this->csrfTokenManager->expects($this->never())->method('getToken');
        $this->csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t) => CsrfSubscriber::TOKEN_ID === $t->getId()
                && 'attacker-supplied-value' === $t->getValue()))
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('CSRF token invalid', ['uri' => self::TEST_URL]);

        $this->subscriber->onKernelRequest($requestEvent);
    }
}
